<?php
// cron/post-publisher.php
// Add this to your cron job: * * * * * /usr/bin/php /path/to/your/project/cron/post-publisher.php

require_once '../config/database-config.php';

echo "Starting automated post publisher...\n";

try {
    // Get pending posts that should be published now
    $stmt = $db->prepare("
        SELECT cgp.*, clt.access_token, c.name as customer_name
        FROM customer_generated_posts cgp
        JOIN customers c ON cgp.customer_id = c.id
        LEFT JOIN customer_linkedin_tokens clt ON c.id = clt.customer_id
        WHERE cgp.status = 'pending' 
        AND cgp.scheduled_time <= NOW()
        ORDER BY cgp.scheduled_time ASC
        LIMIT 10
    ");
    $stmt->execute();
    $pendingPosts = $stmt->fetchAll();
    
    echo "Found " . count($pendingPosts) . " posts to publish\n";
    
    foreach ($pendingPosts as $post) {
        try {
            // Check if customer has LinkedIn token
            if (empty($post['access_token'])) {
                // Update status to failed
                $updateStmt = $db->prepare("
                    UPDATE customer_generated_posts 
                    SET status = 'failed', error_message = 'LinkedIn not connected', updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$post['id']]);
                
                echo "❌ Post {$post['id']} failed - LinkedIn not connected for {$post['customer_name']}\n";
                continue;
            }
            
            // Publish to LinkedIn
            $result = publishToLinkedIn($post['content'], $post['access_token']);
            
            if ($result['success']) {
                // Update as posted
                $updateStmt = $db->prepare("
                    UPDATE customer_generated_posts 
                    SET status = 'posted', linkedin_post_id = ?, posted_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$result['post_id'], $post['id']]);
                
                echo "✅ Post {$post['id']} published successfully for {$post['customer_name']}\n";
                
                // Log activity
                logCustomerActivity($post['customer_id'], 'post_published', 'Automated post published to LinkedIn');
                
            } else {
                // Update as failed
                $updateStmt = $db->prepare("
                    UPDATE customer_generated_posts 
                    SET status = 'failed', error_message = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$result['error'], $post['id']]);
                
                echo "❌ Post {$post['id']} failed - {$result['error']}\n";
            }
            
            // Add delay between posts to avoid rate limiting
            sleep(2);
            
        } catch (Exception $e) {
            echo "❌ Error processing post {$post['id']}: " . $e->getMessage() . "\n";
            
            // Update as failed
            $updateStmt = $db->prepare("
                UPDATE customer_generated_posts 
                SET status = 'failed', error_message = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$e->getMessage(), $post['id']]);
        }
    }
    
    echo "✅ Post publishing completed\n";
    
} catch (Exception $e) {
    echo "❌ Critical error: " . $e->getMessage() . "\n";
    error_log("Cron post publisher error: " . $e->getMessage());
}

function publishToLinkedIn($content, $accessToken) {
    try {
        // First, get the user's LinkedIn ID
        $userInfo = getLinkedInUserProfile($accessToken);
        
        if (!isset($userInfo['id'])) {
            return ['success' => false, 'error' => 'Could not get LinkedIn user ID'];
        }
        
        $personId = $userInfo['id'];
        
        // Prepare post data
        $postData = [
            'author' => 'urn:li:person:' . $personId,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $content
                    ],
                    'shareMediaCategory' => 'NONE'
                ]
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
            ]
        ];
        
        // Make API request to LinkedIn
        $ch = curl_init('https://api.linkedin.com/v2/ugcPosts');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'X-Restli-Protocol-Version: 2.0.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            $result = json_decode($response, true);
            return [
                'success' => true, 
                'post_id' => $result['id'] ?? 'unknown'
            ];
        } else {
            $error = json_decode($response, true);
            return [
                'success' => false, 
                'error' => isset($error['message']) ? $error['message'] : "HTTP $httpCode"
            ];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getLinkedInUserProfile($accessToken) {
    $ch = curl_init('https://api.linkedin.com/v2/people/~');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return [];
}
?>