jQuery(document).ready(function ($) {
	// Test SMTP connection
	$("#test-smtp").on("click", function (e) {
		e.preventDefault();

		var button = $(this);
		var originalText = button.text();
		var testResult = $("#test-result");
		var testOutput = $("#test-output");

		// Show loading state
		button.text("Testing...").prop("disabled", true);
		button.after('<span class="spinner"></span>');
		testResult.show().removeClass("success error");
		testOutput.html("Testing SMTP connection...");

		// Prepare data
		var formData = {
			action: "test_smtp",
			nonce: scm_ajax.nonce,
		};

		// Send AJAX request
		$.ajax({
			url: scm_ajax.ajax_url,
			method: "POST",
			data: formData,
			timeout: 30000, // 30 seconds timeout
			success: function (response) {
				if (response.success) {
					testResult.addClass("success");

					var messageContent = response.data.message;

					// Check if this is an HTML test
					if (response.data.html_test) {
						// Format HTML test response with better styling
						testOutput.html(
							"<div class='html-test-success'>" +
								"<h3>🎉 HTML Email Test Successful!</h3>" +
								"<div class='test-message'>" +
								messageContent.replace(/\n/g, "<br>") +
								"</div>" +
								"<div class='test-instructions'>" +
								"<h4>📋 Next Steps:</h4>" +
								"<ol>" +
								"<li>Check your email inbox for the test email</li>" +
								"<li>Verify that HTML formatting is displayed correctly</li>" +
								"<li>Look for colors, tables, links, and styling</li>" +
								"<li>If everything looks good, your SMTP supports HTML emails!</li>" +
								"</ol>" +
								"</div>" +
								"</div>" +
								"<details class='debug-details' open>" +
								"<summary><strong>🔧 Technical Debug Output</strong> (Click to expand)</summary>" +
								"<pre>" +
								(response.data.debug || "No debug output") +
								"</pre>" +
								"</details>"
						);
					} else {
						// Regular test response
						testOutput.html(
							"<strong>✅ Success!</strong><br>" +
								messageContent +
								"<br><br>" +
								"<strong>Debug Output:</strong><br>" +
								"<pre>" +
								(response.data.debug || "No debug output") +
								"</pre>"
						);
					}
				} else {
					testResult.addClass("error");
					testOutput.html(
						"<div class='html-test-error'>" +
							"<h3>❌ Email Test Failed</h3>" +
							"<div class='error-message'>" +
							response.data.message +
							"</div>" +
							"<div class='troubleshooting'>" +
							"<h4>🔧 Troubleshooting Tips:</h4>" +
							"<ul>" +
							"<li>Verify SMTP host and port settings</li>" +
							"<li>Check username and password</li>" +
							"<li>Ensure encryption method is correct</li>" +
							"<li>Review debug output below for specific errors</li>" +
							"</ul>" +
							"</div>" +
							"</div>" +
							"<details class='debug-details' open>" +
							"<summary><strong>🔧 Debug Output</strong> (Click to expand)</summary>" +
							"<pre>" +
							(response.data.debug || "No debug output") +
							"</pre>" +
							"</details>"
					);
				}

				// Scroll to test result area with smooth animation
				$("html, body").animate(
					{
						scrollTop: testResult.offset().top - 50,
					},
					800
				);
			},
			error: function (xhr, status, error) {
				testResult.addClass("error");
				if (status === "timeout") {
					testOutput.html(
						"<strong>❌ Timeout!</strong>\nThe test took too long to complete. This might indicate connection issues."
					);
				} else {
					testOutput.html("<strong>❌ AJAX Error!</strong>\n" + error);
				}

				// Scroll to test result area with smooth animation
				$("html, body").animate(
					{
						scrollTop: testResult.offset().top - 50,
					},
					800
				);
			},
			complete: function () {
				// Restore button state
				button.text(originalText).prop("disabled", false);
				$(".spinner").remove();
			},
		});
	});

	// Form validation
	$("form").on("submit", function (e) {
		var valid = true;
		var errors = [];

		// Check required fields
		var requiredFields = [
			{ field: "scm_smtp_host", name: "SMTP Host" },
			{ field: "scm_smtp_port", name: "SMTP Port" },
			{ field: "scm_smtp_username", name: "Username" },
			{ field: "scm_smtp_password", name: "Password" },
			{ field: "scm_smtp_from_email", name: "From Email" },
			{ field: "scm_smtp_from_name", name: "From Name" },
		];

		requiredFields.forEach(function (item) {
			var value = $('input[name="' + item.field + '"]')
				.val()
				.trim();
			if (!value) {
				errors.push(item.name + " is required");
				valid = false;
			}
		});

		// Validate email format
		var fromEmail = $(
			'input[name="' + SCM_PLUGIN_PREFIX + 'scm_smtp_from_email"]'
		)
			.val()
			.trim();
		if (fromEmail && !isValidEmail(fromEmail)) {
			errors.push("From Email must be a valid email address");
			valid = false;
		}

		// Validate port number
		var port = parseInt(
			$('input[name="' + SCM_PLUGIN_PREFIX + 'scm_smtp_port"]').val()
		);
		if (isNaN(port) || port < 1 || port > 65535) {
			errors.push("Port must be a number between 1 and 65535");
			valid = false;
		}

		if (!valid) {
			e.preventDefault();
			alert("Please fix the following errors:\n\n" + errors.join("\n"));
		}
	});

	// Email validation function
	function isValidEmail(email) {
		var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return emailRegex.test(email);
	}

	// Toggle password visibility
	$(
		'<button type="button" class="button button-small" style="margin-left: 5px;">Show</button>'
	)
		.insertAfter('input[name="' + SCM_PLUGIN_PREFIX + 'scm_smtp_password"]')
		.on("click", function () {
			var passwordField = $(
				'input[name="' + SCM_PLUGIN_PREFIX + 'scm_smtp_password"]'
			);
			var button = $(this);

			if (passwordField.attr("type") === "password") {
				passwordField.attr("type", "text");
				button.text("Hide");
			} else {
				passwordField.attr("type", "password");
				button.text("Show");
			}
		});

	// Port and encryption suggestions
	$('select[name="' + SCM_PLUGIN_PREFIX + 'scm_smtp_encryption"]').on(
		"change",
		function () {
			var encryption = $(this).val();
			var portField = $('input[name="' + SCM_PLUGIN_PREFIX + 'scm_smtp_port"]');

			if (encryption === "tls" && portField.val() === "465") {
				portField.val("587");
			} else if (encryption === "ssl" && portField.val() === "587") {
				portField.val("465");
			}
		}
	);

	$('input[name="' + SCM_PLUGIN_PREFIX + 'scm_smtp_port"]').on(
		"change",
		function () {
			var port = $(this).val();
			var encryptionField = $(
				'select[name="' + SCM_PLUGIN_PREFIX + 'scm_smtp_encryption"]'
			);

			if (port === "587" && encryptionField.val() === "ssl") {
				encryptionField.val("tls");
			} else if (port === "465" && encryptionField.val() === "tls") {
				encryptionField.val("ssl");
			}
		}
	);

	// Save settings confirmation
	var originalFormData = $("form").serialize();

	$("form").on("submit", function () {
		var currentFormData = $(this).serialize();
		if (originalFormData !== currentFormData) {
			return confirm("Are you sure you want to save these SMTP settings?");
		}
		return true;
	});

	// Show/hide advanced settings
	$(
		'<p><a href="#" id="toggle-advanced">Show Advanced Settings</a></p>'
	).insertAfter(".smtp-setting-table");

	// Hide debug setting by default
	$('select[name="' + SCM_PLUGIN_PREFIX + 'scm_smtp_debug"]')
		.closest("tr")
		.hide();

	$("#toggle-advanced").on("click", function (e) {
		e.preventDefault();
		var debugRow = $(
			'select[name="' + SCM_PLUGIN_PREFIX + 'scm_smtp_debug"]'
		).closest("tr");
		var link = $(this);

		if (debugRow.is(":visible")) {
			debugRow.hide();
			link.text("Show Advanced Settings");
		} else {
			debugRow.show();
			link.text("Hide Advanced Settings");
		}
	});

	// Auto-save draft while typing (with debounce)
	var saveTimeout;
	$("input, select").on("input change", function () {
		clearTimeout(saveTimeout);
		saveTimeout = setTimeout(function () {
			// Could implement auto-save functionality here
			console.log("Settings changed - ready to auto-save");
		}, 2000);
	});

	// Preview email functionality
	$("#preview-email").on("click", function (e) {
		e.preventDefault();

		var subject = $(
			'input[name="' + SCM_PLUGIN_PREFIX + 'scm_email_subject"]'
		).val();
		var content = "";

		// Get content from TinyMCE if available
		if (typeof tinyMCE !== "undefined" && tinyMCE.get("scm_email_content")) {
			content = tinyMCE.get("scm_email_content").getContent();
		} else {
			content = $("#scm_email_content").val();
		}

		// Replace placeholders with sample data
		var sampleData = {
			"{SITE_NAME}": "Your Website",
			"{USER_EMAIL}": "user@example.com",
			"{USER_NAME}": "John Doe",
			"{UNSUBSCRIBE_URL}": "https://yoursite.com/unsubscribe?token=sample",
		};

		var previewContent = content;
		for (var placeholder in sampleData) {
			previewContent = previewContent.replace(
				new RegExp(placeholder, "g"),
				sampleData[placeholder]
			);
		}

		// Show preview
		$("#email-preview-content").html(previewContent);
		$("#email-preview").show();

		// Scroll to preview
		$("#email-preview")[0].scrollIntoView({ behavior: "smooth" });
	});

	// Send test email functionality
	$("#send-test-email").on("click", function (e) {
		e.preventDefault();

		var button = $(this);
		var originalText = button.text();
		var testResult = $("#test-result");
		var testOutput = $("#test-output");

		// Show loading state
		button.text("Sending...").prop("disabled", true);
		button.after('<span class="spinner"></span>');
		testResult.show().removeClass("success error");
		testOutput.html("Sending test email...");

		// Get content from TinyMCE if available
		var content = "";
		if (typeof tinyMCE !== "undefined" && tinyMCE.get("scm_email_content")) {
			content = tinyMCE.get("scm_email_content").getContent();
		} else {
			content = $("#scm_email_content").val();
		}

		// Prepare data
		var formData = {
			action: "send_test_email",
			nonce: scm_ajax.nonce,
			subject: $(
				'input[name="' + SCM_PLUGIN_PREFIX + 'scm_email_subject"]'
			).val(),
			content: content,
		};

		// Send AJAX request
		$.ajax({
			url: scm_ajax.ajax_url,
			method: "POST",
			data: formData,
			success: function (response) {
				if (response.success) {
					testResult.addClass("success");
					testOutput.html(
						"<strong>✅ Test Email Sent!</strong>\n" + response.data.message
					);
				} else {
					testResult.addClass("error");
					testOutput.html(
						"<strong>❌ Failed to Send Test Email!</strong>\n" +
							response.data.message
					);
				}

				// Scroll to test result area with smooth animation
				$("html, body").animate(
					{
						scrollTop: testResult.offset().top - 50,
					},
					800
				);
			},
			error: function (xhr, status, error) {
				testResult.addClass("error");
				testOutput.html(
					"<strong>❌ Error Sending Test Email!</strong>\n" + error
				);

				// Scroll to test result area with smooth animation
				$("html, body").animate(
					{
						scrollTop: testResult.offset().top - 50,
					},
					800
				);
			},
			complete: function () {
				// Restore button state
				button.text(originalText).prop("disabled", false);
				$(".spinner").remove();
			},
		});
	});
});
