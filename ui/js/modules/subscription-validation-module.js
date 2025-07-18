// /js/subscription-validation-module.js
var SubscriptionValidationModule = (function() {
    async function validateSubscription() {
        console.log("SubscriptionValidationModule: Starting validation..."); // ADDED LOG

        try {
            const response = await fetch('/includes/validate_subscription.php');
            console.log("SubscriptionValidationModule: fetch() completed; status:", response.status); // ADDED LOG

            const data = await response.json();
            console.log("SubscriptionValidationModule: parsed response data:", data); // ADDED LOG
            
            if (response.status === 401 || !data.isValid) {
                console.log("SubscriptionValidationModule: subscription invalid; redirecting to /subscription.php"); // ADDED LOG
                window.location.href = '/subscription.php';
                return false;
            }

            console.log("SubscriptionValidationModule: subscription is valid."); // ADDED LOG
            return true;

        } catch (error) {
            console.error('SubscriptionValidationModule: Error validating subscription:', error);
            return false;
        }
    }
    return {
        validateSubscription: validateSubscription
    };
})();
