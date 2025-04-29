<?php
/**
 * Helper Functions for Expense Maker
 */

/**
 * Format a number as currency
 * @param float $amount The amount to format
 * @param string $currency The currency code (default: USD)
 * @return string Formatted currency string
 */
function formatCurrency($amount, $currency = 'USD') {
    return number_format($amount, 2, '.', ',');
}

/**
 * Get relative time difference
 * @param string $date The date to compare
 * @return string Relative time string (e.g., "2 hours ago")
 */
function getRelativeTime($date) {
    $timestamp = strtotime($date);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'just now';
    } elseif ($difference < 3600) {
        $minutes = round($difference / 60);
        return $minutes . ' minute' . ($minutes == 1 ? '' : 's') . ' ago';
    } elseif ($difference < 86400) {
        $hours = round($difference / 3600);
        return $hours . ' hour' . ($hours == 1 ? '' : 's') . ' ago';
    } elseif ($difference < 604800) {
        $days = round($difference / 86400);
        return $days . ' day' . ($days == 1 ? '' : 's') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

/**
 * Get user's avatar URL or initial
 * @param array $user User data array
 * @return string Avatar URL or initial
 */
function getUserAvatar($user) {
    if (!empty($user['avatar_url'])) {
        return htmlspecialchars($user['avatar_url']);
    }
    return strtoupper(substr($user['name'], 0, 1));
}

/**
 * Calculate split amount for an expense
 * @param float $amount Total amount
 * @param int $members Number of members
 * @param string $split_type Type of split (equal, percentage, custom)
 * @param array $custom_splits Custom split amounts if applicable
 * @return array Array of split amounts
 */
function calculateSplitAmount($amount, $members, $split_type = 'equal', $custom_splits = []) {
    $splits = [];
    
    switch ($split_type) {
        case 'equal':
            $split_amount = $amount / $members;
            for ($i = 0; $i < $members; $i++) {
                $splits[] = round($split_amount, 2);
            }
            break;
            
        case 'percentage':
            $total_percentage = array_sum($custom_splits);
            if ($total_percentage != 100) {
                throw new Exception('Total percentage must equal 100');
            }
            foreach ($custom_splits as $percentage) {
                $splits[] = round(($amount * $percentage) / 100, 2);
            }
            break;
            
        case 'custom':
            $total_custom = array_sum($custom_splits);
            if ($total_custom != $amount) {
                throw new Exception('Total custom amounts must equal expense amount');
            }
            $splits = array_map(function($split) {
                return round($split, 2);
            }, $custom_splits);
            break;
            
        default:
            throw new Exception('Invalid split type');
    }
    
    return $splits;
}

/**
 * Get user's balance in a group
 * @param int $user_id User ID
 * @param int $group_id Group ID
 * @return array Balance information
 */
function getUserGroupBalance($user_id, $group_id) {
    global $pdo;
    
    // Get what user owes to others
    $stmt = $pdo->prepare("
        SELECT SUM(es.amount) as owes
        FROM expense_splits es
        JOIN expenses e ON es.expense_id = e.id
        WHERE es.user_id = ? 
        AND e.group_id = ? 
        AND e.paid_by != ?
        AND es.is_settled = 0
    ");
    $stmt->execute([$user_id, $group_id, $user_id]);
    $owes = $stmt->fetch()['owes'] ?? 0;
    
    // Get what others owe to user
    $stmt = $pdo->prepare("
        SELECT SUM(es.amount) as owed
        FROM expense_splits es
        JOIN expenses e ON es.expense_id = e.id
        WHERE e.group_id = ? 
        AND e.paid_by = ? 
        AND es.user_id != ?
        AND es.is_settled = 0
    ");
    $stmt->execute([$group_id, $user_id, $user_id]);
    $owed = $stmt->fetch()['owed'] ?? 0;
    
    return [
        'owes' => $owes,
        'owed' => $owed,
        'net' => $owed - $owes
    ];
}

/**
 * Check if user is member of a group
 * @param int $user_id User ID
 * @param int $group_id Group ID
 * @return bool True if user is member, false otherwise
 */
function isGroupMember($user_id, $group_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM group_members 
        WHERE user_id = ? AND group_id = ?
    ");
    $stmt->execute([$user_id, $group_id]);
    return $stmt->fetch()['count'] > 0;
}

/**
 * Get expense categories with icons
 * @return array Array of categories with their icons
 */
function getExpenseCategories() {
    return [
        'food' => ['name' => 'Food & Drinks', 'icon' => 'fas fa-utensils'],
        'transport' => ['name' => 'Transport', 'icon' => 'fas fa-car'],
        'shopping' => ['name' => 'Shopping', 'icon' => 'fas fa-shopping-bag'],
        'bills' => ['name' => 'Bills & Utilities', 'icon' => 'fas fa-file-invoice-dollar'],
        'entertainment' => ['name' => 'Entertainment', 'icon' => 'fas fa-film'],
        'travel' => ['name' => 'Travel', 'icon' => 'fas fa-plane'],
        'health' => ['name' => 'Health', 'icon' => 'fas fa-medkit'],
        'education' => ['name' => 'Education', 'icon' => 'fas fa-graduation-cap'],
        'other' => ['name' => 'Other', 'icon' => 'fas fa-ellipsis-h']
    ];
}

/**
 * Validate and sanitize expense data
 * @param array $data Post data
 * @return array Sanitized data or false if validation fails
 */
function validateExpenseData($data) {
    $errors = [];
    $sanitized = [];
    
    // Required fields
    $required = ['group_id', 'amount', 'description'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst($field) . ' is required';
        }
    }
    
    // Group ID
    $sanitized['group_id'] = filter_var($data['group_id'], FILTER_VALIDATE_INT);
    if ($sanitized['group_id'] === false) {
        $errors[] = 'Invalid group ID';
    }
    
    // Amount
    $sanitized['amount'] = filter_var($data['amount'], FILTER_VALIDATE_FLOAT);
    if ($sanitized['amount'] === false || $sanitized['amount'] <= 0) {
        $errors[] = 'Invalid amount';
    }
    
    // Description
    $sanitized['description'] = trim(strip_tags($data['description']));
    if (strlen($sanitized['description']) < 3) {
        $errors[] = 'Description must be at least 3 characters';
    }
    
    // Category
    $categories = array_keys(getExpenseCategories());
    $sanitized['category'] = !empty($data['category']) ? strtolower($data['category']) : 'other';
    if (!in_array($sanitized['category'], $categories)) {
        $sanitized['category'] = 'other';
    }
    
    // Date
    $sanitized['date'] = !empty($data['date']) ? date('Y-m-d', strtotime($data['date'])) : date('Y-m-d');
    if ($sanitized['date'] === false) {
        $errors[] = 'Invalid date';
    }
    
    return empty($errors) ? $sanitized : ['errors' => $errors];
}
