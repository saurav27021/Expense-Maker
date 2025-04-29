<?php
include 'razorpay-config.php';
?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<button id="payBtn">Pay Now</button>
<script>
document.getElementById('payBtn').onclick = function() {
    var options = {
        "key": "<?php echo $apiKey; ?>",
        "amount": "50000",
        "currency": "INR",
        "name": "Expense Maker",
        "description": "Bill Payment",
        "handler": function (response) {
            alert("Payment Successful: " + response.razorpay_payment_id);
        }
    };
    var rzp = new Razorpay(options);
    rzp.open();
};
</script>
