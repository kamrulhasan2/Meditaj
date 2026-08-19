/**
 * Settings screen: reveal and re-hide the stored gateway and Agora secrets.
 *
 * @package EG Care
 */

document.addEventListener('DOMContentLoaded', function() {
	const toggleBtn = document.getElementById('toggle-password-visibility');
	const pwdInput = document.getElementById('eg_care_ssl_store_passwd');
	if (toggleBtn && pwdInput) {
		toggleBtn.addEventListener('click', function() {
			if (pwdInput.type === 'password') {
				pwdInput.type = 'text';
				toggleBtn.classList.remove('dashicons-visibility');
				toggleBtn.classList.add('dashicons-hidden');
			} else {
				pwdInput.type = 'password';
				toggleBtn.classList.remove('dashicons-hidden');
				toggleBtn.classList.add('dashicons-visibility');
			}
		});
	}

	const toggleAgoraBtn = document.getElementById('toggle-agora-visibility');
	const agoraInput = document.getElementById('eg_care_agora_app_certificate');
	if (toggleAgoraBtn && agoraInput) {
		toggleAgoraBtn.addEventListener('click', function() {
			if (agoraInput.type === 'password') {
				agoraInput.type = 'text';
				toggleAgoraBtn.classList.remove('dashicons-visibility');
				toggleAgoraBtn.classList.add('dashicons-hidden');
			} else {
				agoraInput.type = 'password';
				toggleAgoraBtn.classList.remove('dashicons-hidden');
				toggleAgoraBtn.classList.add('dashicons-visibility');
			}
		});
	}
});
