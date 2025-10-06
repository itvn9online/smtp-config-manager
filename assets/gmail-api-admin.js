/**
 * Gmail API Admin JavaScript
 * Xử lý interface và AJAX calls cho Gmail API settings
 */

jQuery(document).ready(function ($) {
	// Email validation function (used for prompt validation)
	function isValidEmail(email) {
		var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return emailRegex.test(email);
	}

	// Get nonces for different actions
	var gmailTestNonce =
		typeof scmGmailAjax !== "undefined" ? scmGmailAjax.test_nonce : "";
	var gmailAjaxNonce =
		typeof scmGmailAjax !== "undefined" ? scmGmailAjax.nonce : "";
	// Toggle email method settings
	$('input[name$="scm_email_method"]').change(function () {
		var selectedMethod = $(this).val();

		if (selectedMethod === "gmail_api") {
			$("#smtp-settings").hide();
			$("#gmail-api-settings").show();

			// Show/hide test buttons
			$("#test-smtp").hide();
			$("#test-gmail-api").show();
		} else {
			$("#gmail-api-settings").hide();
			$("#smtp-settings").show();

			// Show/hide test buttons
			$("#test-gmail-api").hide();
			$("#test-smtp").show();
		}
	});

	// Trigger change on page load to set initial state
	$('input[name$="scm_email_method"]:checked').trigger("change");

	// Gmail authorization buttons (both authorize and re-authorize)
	$("#gmail-authorize, #gmail-reauth").click(function (e) {
		e.preventDefault();

		var clientId = $('input[name="gmail_client_id"]').val();
		var clientSecret = $('input[name="gmail_client_secret"]').val();

		if (!clientId || !clientSecret) {
			alert(
				"Vui lòng nhập Client ID và Client Secret trước, sau đó lưu settings."
			);
			return;
		}

		// Generate Google OAuth URL directly (simplified approach)
		var redirectUri = encodeURIComponent(
			window.location.origin +
				window.location.pathname +
				"?page=smtp-config-manager&action=gmail_callback"
		);
		var scope = encodeURIComponent(
			"https://www.googleapis.com/auth/gmail.modify"
		);
		var authUrl =
			"https://accounts.google.com/o/oauth2/auth?" +
			"client_id=" +
			encodeURIComponent(clientId) +
			"&redirect_uri=" +
			redirectUri +
			"&scope=" +
			scope +
			"&response_type=code" +
			"&access_type=offline" +
			"&prompt=consent";

		// Open Google OAuth in new window
		console.log("Opening auth URL:", authUrl); // For debugging
		window.open(authUrl, "_blank");
	});

	// Test Gmail API connection
	$("#test-gmail-api").click(function (e) {
		e.preventDefault();

		// Prompt user for test email address
		var testEmail = prompt(
			"📧 Enter email address to receive the Gmail API test email:\n" +
				"(Leave empty to use admin email)",
			""
		);

		// If user cancelled the prompt, exit
		if (testEmail === null) {
			return;
		}

		// Validate email format if provided
		if (testEmail.trim() !== "" && !isValidEmail(testEmail.trim())) {
			alert("❌ Please enter a valid email address!");
			return;
		}

		var $button = $(this);
		var $result = $("#test-result");
		var $output = $("#test-output");

		// Show loading
		$button.prop("disabled", true).text("Testing...");
		$result.show().removeClass("success error");
		$output.html("<p>Testing Gmail API connection...</p>");

		// Test Gmail API
		$.post(
			scmGmailAjax.ajax_url,
			{
				action: "test_gmail_api",
				nonce: gmailTestNonce,
				test_email: testEmail.trim(), // Add custom email to the request
			},
			function (response) {
				$button.prop("disabled", false).text("Test Gmail API");

				if (response.success) {
					$result.addClass("success");
					var messageContent = response.data.message || response.data;

					$output.html(
						'<div class="gmail-test-success">' +
							"<h3>🎉 Gmail API Test Successful!</h3>" +
							'<div class="test-message">' +
							messageContent +
							"</div>" +
							'<div class="test-instructions">' +
							"<h4>📋 Next Steps:</h4>" +
							"<ol>" +
							"<li>Check your email inbox for the test email</li>" +
							"<li>Verify that the email was sent via Gmail API</li>" +
							"<li>Look for Gmail API specific formatting</li>" +
							"<li>If everything looks good, your Gmail API is working!</li>" +
							"</ol>" +
							"</div>" +
							"</div>"
					);

					if (response.data.email) {
						$output.append(
							"<p><strong>Authenticated Gmail:</strong> " +
								response.data.email +
								"</p>"
						);
					}
				} else {
					$result.addClass("error");
					var errorMessage =
						response.data.message || response.data || "Unknown error";

					$output.html(
						'<div class="gmail-test-error">' +
							"<h3>❌ Gmail API Test Failed</h3>" +
							'<div class="error-message">' +
							errorMessage +
							"</div>" +
							'<div class="troubleshooting">' +
							"<h4>🔧 Troubleshooting Tips:</h4>" +
							"<ul>" +
							"<li>Verify Gmail API credentials are correct</li>" +
							"<li>Check if Gmail API is properly authenticated</li>" +
							"<li>Ensure refresh token is valid</li>" +
							"<li>Review the error message above for specific issues</li>" +
							"</ul>" +
							"</div>" +
							"</div>"
					);
				}

				// Scroll to test result area with smooth animation
				$("html, body").animate(
					{
						scrollTop: $result.offset().top - 50,
					},
					800
				);
			}
		).fail(function () {
			$button.prop("disabled", false).text("Test Gmail API");
			$result.addClass("error");
			$output.html(
				'<div class="gmail-test-error">' +
					"<h3>❌ Connection Failed</h3>" +
					'<div class="error-message">AJAX request failed. Please check your connection and try again.</div>' +
					"</div>"
			);
		});
	});
});
