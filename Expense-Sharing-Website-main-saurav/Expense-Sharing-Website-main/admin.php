<?php
include 'db.php';

$sql = "SELECT users.name, expenses.category, expenses.amount, expenses.date 
        FROM users 
        JOIN expenses ON users.id = expenses.user_id 
        ORDER BY expenses.date DESC";
$result = $conn->query($sql);
?>
<table>
    <tr>
        <th><?php echo "User"; ?></th>
        <th><?php echo "Category"; ?></th>
        <th><?php echo "Amount"; ?></th>
        <th><?php echo "Date"; ?></th>
    </tr>
    <?php while ($row = $result->fetch_assoc()) { ?>
    <tr>
        <td><?php echo $row['name']; ?></td>
        <td><?php echo $row['category']; ?></td>
        <td><?php echo $row['amount']; ?></td>
        <td><?php echo $row['date']; ?></td>
    </tr>
    <?php } ?>
</table>
