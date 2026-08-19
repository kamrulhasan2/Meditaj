/**
 * Add Doctor screen: show the payout fields that match the chosen method.
 *
 * @package EG Care
 */

document.addEventListener('DOMContentLoaded', function() {
	const payoutType = document.getElementById('payout_type');
	const bankSec = document.getElementById('sec_bank_fields');
	const mobileSec = document.getElementById('sec_mobile_fields');

	function togglePayoutFields() {
		if (payoutType.value === 'bank') {
			bankSec.style.display = 'grid';
			mobileSec.style.display = 'none';
		} else {
			bankSec.style.display = 'none';
			mobileSec.style.display = 'grid';
		}
	}

	payoutType.addEventListener('change', togglePayoutFields);
	togglePayoutFields();
});
