// ===== DONATION PAGE INITIALIZATION =====
document.addEventListener("DOMContentLoaded", function () {
  // Setup responsive handling
  setupResponsiveHandling();

  // Initialize donation form if it exists
  if (document.getElementById("donation-form")) {
    initializeDonationForm();
  }

  // Initialize donation success page if it exists
  if (document.querySelector(".success-container")) {
    initializeDonationSuccess();
  }

  // Setup smooth scrolling and animations
  setupSmoothAnimations();

  // Initialize form validation
  setupFormValidation();

  console.log("Donation page loaded and scripts initialized");
});

// ===== RESPONSIVE HANDLING =====
function setupResponsiveHandling() {
  // Handle orientation changes
  window.addEventListener("orientationchange", function () {
    setTimeout(() => {
      adjustForMobile();
    }, 100);
  });

  // Handle resize
  window.addEventListener("resize", adjustForMobile);

  // Initial adjustment
  adjustForMobile();
}

function adjustForMobile() {
  const isMobile = window.innerWidth <= 768;
  const container = document.querySelector(".container");

  if (container) {
    if (isMobile) {
      container.style.padding = "15px";
    } else {
      container.style.padding = "20px";
    }
  }

  // Adjust payment methods layout on mobile
  const paymentMethods = document.querySelector(".payment-methods");
  if (paymentMethods && isMobile) {
    paymentMethods.style.gridTemplateColumns = "1fr";
  }
}

// ===== DONATION FORM INITIALIZATION =====
function initializeDonationForm() {
  // Update the display amount when the input changes
  const amountInput = document.getElementById("amount");
  const displayAmount = document.getElementById("display-amount");

  if (amountInput && displayAmount) {
    amountInput.addEventListener("input", function () {
      const value = parseFloat(this.value) || 0;
      displayAmount.textContent = value.toFixed(2);

      // Add visual feedback for amount changes
      displayAmount.style.transform = "scale(1.1)";
      displayAmount.style.color = "var(--donation-primary)";
      setTimeout(() => {
        displayAmount.style.transform = "scale(1)";
        displayAmount.style.color = "";
      }, 200);
    });

    // Validate minimum amount
    amountInput.addEventListener("blur", function () {
      const value = parseFloat(this.value) || 0;
      if (value < 1) {
        this.value = "1.00";
        displayAmount.textContent = "1.00";
        showMessage("Minimum donation amount is $1.00", "warning");
      }
    });
  }

  // Payment method selection
  const paymentMethods = document.querySelectorAll(".payment-method");
  const paymentFields = {
    credit_card: document.getElementById("card-fields"),
    debit_card: document.getElementById("card-fields"),
    mobile_banking: document.getElementById("mobile-fields"),
    bank_transfer: document.getElementById("bank-fields"),
  };

  paymentMethods.forEach((method) => {
    method.addEventListener("click", function () {
      // Select the radio button
      const radio = this.querySelector('input[type="radio"]');
      radio.checked = true;

      // Highlight the selected method with animation
      paymentMethods.forEach((m) => {
        m.classList.remove("selected");
        m.style.transform = "scale(1)";
      });

      this.classList.add("selected");
      this.style.transform = "scale(1.02)";
      setTimeout(() => {
        this.style.transform = "scale(1)";
      }, 200);

      // Show the appropriate fields with smooth animation
      const methodName = this.dataset.method;
      Object.values(paymentFields).forEach((field) => {
        if (field) {
          field.classList.remove("active");
          field.style.display = "none";
        }
      });

      if (paymentFields[methodName]) {
        setTimeout(() => {
          paymentFields[methodName].style.display = "block";
          paymentFields[methodName].classList.add("active");
        }, 100);
      }

      // Enable/disable required fields based on payment method
      toggleRequiredFields(methodName);

      // Show success message
      showMessage("Payment method selected", "success");
    });
  });

  // Anonymous Donation checkbox handling
  const anonymousCheckbox = document.getElementById("anonymous_donation");
  const guestFields = document.getElementById("guest-fields"); // This div contains name and email fields

  if (anonymousCheckbox && guestFields) {
    // Initial state
    if (anonymousCheckbox.checked) {
      guestFields.classList.add("hidden");
      setGuestFieldsRequired(false);
    } else {
      guestFields.classList.add("visible");
      setGuestFieldsRequired(true);
    }

    anonymousCheckbox.addEventListener("change", function () {
      if (this.checked) {
        guestFields.classList.remove("visible");
        guestFields.classList.add("hidden");
        setTimeout(() => {
          guestFields.style.display = "none";
        }, 300); // Wait for transition
        setGuestFieldsRequired(false);
      } else {
        guestFields.style.display = "block";
        guestFields.classList.remove("hidden");
        guestFields.classList.add("visible");
        setGuestFieldsRequired(true);
      }
    });
  } else if (guestFields) {
    // If anonymous checkbox doesn't exist but guest fields do (e.g., user is always a guest)
    setGuestFieldsRequired(true); // Always required if no anonymous option
  }

  // Form submission handling
  const donationForm = document.getElementById("donation-form");
  if (donationForm) {
    donationForm.addEventListener("submit", function (e) {
      // Validate form before submission
      if (!validateDonationForm()) {
        e.preventDefault();
        return false;
      }

      // Show loading state
      const submitBtn = this.querySelector(".submit-btn");
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Processing Payment...';
      submitBtn.disabled = true;

      // Show processing message
      showMessage("Processing your donation, please wait...", "info");

      // Allow form to submit normally to process-donation.php
      // No preventDefault() - let the form submit naturally

      // Note: The button will be reset by page navigation or if user goes back
    });
  }
}

// Function to set required attribute for guest fields
function setGuestFieldsRequired(required) {
  const nameField = document.getElementById("name");
  const emailField = document.getElementById("email");
  if (nameField) {
    nameField.required = required;
    if (!required) {
      nameField.style.borderColor = "";
      nameField.style.boxShadow = "";
    }
  }
  if (emailField) {
    emailField.required = required;
    if (!required) {
      emailField.style.borderColor = "";
      emailField.style.boxShadow = "";
    }
  }
}

// ===== FORM VALIDATION =====
function setupFormValidation() {
  // Real-time validation for inputs
  const inputs = document.querySelectorAll("input, select");

  inputs.forEach((input) => {
    input.addEventListener("blur", function () {
      // Clear error styling on focus
      this.style.borderColor = "";
      this.style.boxShadow = "";
    });

    input.addEventListener("input", function () {
      // Clear validation styling on input
      this.style.borderColor = "";
      this.style.boxShadow = "";
    });
  });
}

function validateField(field) {
  const isValid = field.value.trim() !== "";

  if (!isValid) {
    field.style.borderColor = "var(--danger-color)";
    field.style.boxShadow = "0 0 0 3px rgba(220, 53, 69, 0.1)";
    return false;
  } else {
    field.style.borderColor = "var(--success-color)";
    field.style.boxShadow = "0 0 0 3px rgba(40, 167, 69, 0.1)";
    return true;
  }
}

function validateDonationForm() {
  const form = document.getElementById("donation-form");
  const selectedMethod = document.querySelector(
    'input[name="payment_method"]:checked'
  );
  const amountInput = document.getElementById("amount");
  const anonymousCheckbox = document.getElementById("anonymous_donation");

  let isValid = true;

  // Check amount first
  if (!amountInput || parseFloat(amountInput.value) <= 0) {
    showMessage("Please enter a valid donation amount", "warning");
    if (amountInput) {
      amountInput.focus();
      amountInput.style.borderColor = "var(--danger-color)";
    }
    isValid = false;
  }

  // Check payment method selection
  if (!selectedMethod) {
    showMessage("Please select a payment method", "warning");
    isValid = false;
  }

  // Check required fields based on user login status AND anonymous option
  const nameField = document.getElementById("name");
  const emailField = document.getElementById("email");

  // If name and email fields exist (guest user) AND anonymous is NOT checked, validate them
  if (
    nameField &&
    emailField &&
    (!anonymousCheckbox || !anonymousCheckbox.checked)
  ) {
    if (!nameField.value.trim()) {
      showMessage("Please enter your full name", "warning");
      nameField.focus();
      nameField.style.borderColor = "var(--danger-color)";
      isValid = false;
    }

    if (!emailField.value.trim()) {
      showMessage("Please enter your email address", "warning");
      emailField.focus();
      emailField.style.borderColor = "var(--danger-color)";
      isValid = false;
    } else if (!isValidEmail(emailField.value)) {
      showMessage("Please enter a valid email address", "warning");
      emailField.focus();
      emailField.style.borderColor = "var(--danger-color)";
      isValid = false;
    }
  }

  // Validate payment method specific fields
  if (selectedMethod && isValid) {
    const methodName = selectedMethod.value;

    if (methodName === "credit_card" || methodName === "debit_card") {
      const cardNumber = document.getElementById("card_number");
      const expiry = document.getElementById("expiry");
      const cvv = document.getElementById("cvv");

      if (!cardNumber || !cardNumber.value.trim()) {
        showMessage("Please enter your card number", "warning");
        if (cardNumber) cardNumber.focus();
        isValid = false;
      }

      if (!expiry || !expiry.value.trim()) {
        showMessage("Please enter the expiry date", "warning");
        if (expiry) expiry.focus();
        isValid = false;
      }

      if (!cvv || !cvv.value.trim()) {
        showMessage("Please enter the CVV", "warning");
        if (cvv) cvv.focus();
        isValid = false;
      }
    }

    if (methodName === "mobile_banking") {
      const provider = document.getElementById("mobile_provider");
      const mobileNumber = document.getElementById("mobile_number");

      if (!provider || !provider.value) {
        showMessage("Please select your mobile banking provider", "warning");
        if (provider) provider.focus();
        isValid = false;
      }

      if (!mobileNumber || !mobileNumber.value.trim()) {
        showMessage("Please enter your mobile number", "warning");
        if (mobileNumber) mobileNumber.focus();
        isValid = false;
      }
    }

    if (methodName === "bank_transfer") {
      const bankName = document.getElementById("bank_name");
      const accountNumber = document.getElementById("account_number");

      if (!bankName || !bankName.value) {
        showMessage("Please select your bank", "warning");
        if (bankName) bankName.focus();
        isValid = false;
      }

      if (!accountNumber || !accountNumber.value.trim()) {
        showMessage("Please enter your account number", "warning");
        if (accountNumber) accountNumber.focus();
        isValid = false;
      }
    }
  }

  return isValid;
}

// Helper function to validate email
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

// ===== PAYMENT FIELD MANAGEMENT =====
function toggleRequiredFields(method) {
  // Card fields
  const cardFields = ["card_number", "expiry", "cvv"];
  cardFields.forEach((field) => {
    const element = document.getElementById(field);
    if (element) {
      element.required = method === "credit_card" || method === "debit_card";
      if (!element.required) {
        element.style.borderColor = "";
        element.style.boxShadow = "";
      }
    }
  });

  // Mobile banking fields
  const mobileFields = ["mobile_provider", "mobile_number"];
  mobileFields.forEach((field) => {
    const element = document.getElementById(field);
    if (element) {
      element.required = method === "mobile_banking";
      if (!element.required) {
        element.style.borderColor = "";
        element.style.boxShadow = "";
      }
    }
  });

  // Bank transfer fields
  const bankFields = ["bank_name", "account_number"];
  bankFields.forEach((field) => {
    const element = document.getElementById(field);
    if (element) {
      element.required = method === "bank_transfer";
      if (!element.required) {
        element.style.borderColor = "";
        element.style.boxShadow = "";
      }
    }
  });
}

// ===== SMOOTH ANIMATIONS =====
function setupSmoothAnimations() {
  // Animate elements on page load
  const animateElements = document.querySelectorAll(
    ".donation-container, .success-container, .header"
  );
  animateElements.forEach((element, index) => {
    element.style.opacity = "0";
    element.style.transform = "translateY(30px)";

    setTimeout(() => {
      element.style.transition = "all 0.6s ease";
      element.style.opacity = "1";
      element.style.transform = "translateY(0)";
    }, index * 200);
  });

  // Add hover animations to interactive elements
  const interactiveElements = document.querySelectorAll(
    ".payment-method, .btn, .social-btn"
  );
  interactiveElements.forEach((element) => {
    element.addEventListener("mouseenter", function () {
      this.style.transition = "all 0.3s ease";
    });
  });
}

// ===== SUCCESS PAGE FUNCTIONS =====
function initializeDonationSuccess() {
  // Store donation data globally for sharing functions
  const campaignNameElement = document.querySelector(
    ".card-campaign, .campaign-name"
  );
  const donationAmountElement = document.querySelector(
    ".card-amount, .donation-amount"
  );

  if (campaignNameElement && donationAmountElement) {
    window.donationData = {
      campaignName: campaignNameElement.textContent.replace("to ", "").trim(),
      donationAmount: donationAmountElement.textContent.replace("$", "").trim(),
    };
  }

  // Animate success elements
  animateSuccessElements();
}

function animateSuccessElements() {
  const successIcon = document.querySelector(".success-icon");
  const successTitle = document.querySelector(".success-title");
  const donationDetails = document.querySelector(".donation-details");

  if (successIcon) {
    setTimeout(() => {
      successIcon.style.animation = "bounce 1s ease-in-out";
    }, 300);
  }

  if (successTitle) {
    setTimeout(() => {
      successTitle.style.opacity = "1";
      successTitle.style.transform = "translateY(0)";
    }, 600);
  }

  if (donationDetails) {
    setTimeout(() => {
      donationDetails.style.opacity = "1";
      donationDetails.style.transform = "scale(1)";
    }, 900);
  }
}

// ===== SHARING FUNCTIONS =====
async function shareOnFacebook() {
  try {
    const campaignName = window.donationData?.campaignName || "this campaign";
    const donationAmount = window.donationData?.donationAmount || "0.00";
    const currentUrl = window.location.href;

    const shareMessage = `I just donated $${donationAmount} to ${campaignName}. Join me in making a difference!`;
    const url =
      "https://www.facebook.com/sharer/sharer.php?u=" +
      encodeURIComponent(currentUrl) +
      "&quote=" +
      encodeURIComponent(shareMessage);

    window.open(url, "_blank", "width=600,height=400");
    showMessage("Sharing on Facebook...", "info");
  } catch (err) {
    console.error("Error sharing to Facebook:", err);
    showMessage("Error sharing to Facebook. Please try again.", "error");
  }
}

async function shareOnTwitter() {
  try {
    const campaignName = window.donationData?.campaignName || "this campaign";
    const donationAmount = window.donationData?.donationAmount || "0.00";
    const currentUrl = window.location.href;

    const shareMessage = `I just donated $${donationAmount} to ${campaignName}. Join me in making a difference! 💚`;
    const url =
      "https://twitter.com/intent/tweet?text=" +
      encodeURIComponent(shareMessage) +
      "&url=" +
      encodeURIComponent(currentUrl);

    window.open(url, "_blank", "width=600,height=400");
    showMessage("Sharing on Twitter...", "info");
  } catch (err) {
    console.error("Error sharing to Twitter:", err);
    showMessage("Error sharing to Twitter. Please try again.", "error");
  }
}

async function shareOnWhatsApp() {
  try {
    const campaignName = window.donationData?.campaignName || "this campaign";
    const donationAmount = window.donationData?.donationAmount || "0.00";
    const currentUrl = window.location.href;

    const shareMessage = `🎉 I just donated $${donationAmount} to ${campaignName}! Join me in making a difference! 💚\n\n${currentUrl}`;
    const url =
      "https://api.whatsapp.com/send?text=" + encodeURIComponent(shareMessage);

    window.open(url, "_blank");
    showMessage("Opening WhatsApp...", "info");
  } catch (err) {
    console.error("Error sharing to WhatsApp:", err);
    showMessage("Error sharing to WhatsApp. Please try again.", "error");
  }
}

async function shareViaEmail() {
  try {
    const campaignName = window.donationData?.campaignName || "this campaign";
    const donationAmount = window.donationData?.donationAmount || "0.00";
    const currentUrl = window.location.href;

    const shareTitle = `I just donated to ${campaignName}!`;
    const shareMessage = `Hello!\n\nI just made a donation of $${donationAmount} to ${campaignName} and wanted to share this opportunity with you.\n\nEvery contribution makes a difference, and I thought you might be interested in supporting this cause too.\n\nYou can learn more and donate here: ${currentUrl}\n\nThanks for taking the time to consider it!\n\nBest regards`;

    const subject = encodeURIComponent(shareTitle);
    const body = encodeURIComponent(shareMessage);
    window.location.href = "mailto:?subject=" + subject + "&body=" + body;

    showMessage("Opening email client...", "info");
  } catch (err) {
    console.error("Error sharing via email:", err);
    showMessage("Error opening email client. Please try again.", "error");
  }
}

async function downloadPDF() {
  try {
    showMessage("Generating PDF certificate...", "info");

    // Check if required libraries are available
    if (!window.html2canvas || !window.jspdf) {
      showMessage(
        "PDF generation libraries not available. Please refresh the page and try again.",
        "error"
      );
      return;
    }

    // Create image first
    const imageUrl = await createCardImage();
    if (!imageUrl) {
      showMessage(
        "Error generating certificate image. Please try again.",
        "error"
      );
      return;
    }

    // Create PDF using jsPDF
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF("p", "mm", "a4");

    // Add header
    pdf.setFontSize(20);
    pdf.setTextColor(76, 175, 80);
    pdf.text("Donation Certificate", 105, 30, { align: "center" });

    // Add the image
    const imgProps = pdf.getImageProperties(imageUrl);
    const pageWidth = pdf.internal.pageSize.getWidth();
    const imgWidth = pageWidth - 40;
    const imgHeight = (imgProps.height * imgWidth) / imgProps.width;

    pdf.addImage(imageUrl, "PNG", 20, 40, imgWidth, imgHeight);

    // Add footer text
    pdf.setFontSize(10);
    pdf.setTextColor(100, 100, 100);
    pdf.text(
      "Thank you for your generous donation! This certificate confirms your contribution.",
      105,
      imgHeight + 60,
      { align: "center" }
    );
    pdf.text(
      "Generated on: " + new Date().toLocaleString(),
      105,
      imgHeight + 70,
      { align: "center" }
    );

    // Download the PDF
    const campaignName = window.donationData?.campaignName || "Campaign";
    const fileName =
      "Donation_Certificate_" +
      campaignName.replace(/\s+/g, "_") +
      "_" +
      new Date().getFullYear() +
      ".pdf";
    pdf.save(fileName);

    showMessage("PDF certificate downloaded successfully!", "success");
  } catch (err) {
    console.error("Error generating PDF:", err);
    showMessage("Error generating PDF certificate. Please try again.", "error");
  }
}

async function createCardImage() {
  const donationCard = document.getElementById("donation-card");
  if (!donationCard) {
    console.error("Donation card element not found");
    return null;
  }

  // Make the donation card visible temporarily
  donationCard.style.display = "block";
  donationCard.style.position = "absolute";
  donationCard.style.left = "-9999px";

  try {
    const canvas = await html2canvas(donationCard, {
      scale: 2,
      backgroundColor: "#ffffff",
      logging: false,
      useCORS: true,
    });

    const imageUrl = canvas.toDataURL("image/png");
    return imageUrl;
  } catch (error) {
    console.error("Error creating card image:", error);
    return null;
  } finally {
    // Hide the donation card again
    donationCard.style.display = "none";
    donationCard.style.position = "";
    donationCard.style.left = "";
  }
}

// ===== UTILITY FUNCTIONS =====
function showMessage(message, type = "info") {
  // Remove existing messages
  const existingMessages = document.querySelectorAll(".toast-message");
  existingMessages.forEach((msg) => msg.remove());

  // Create message element
  const messageDiv = document.createElement("div");
  messageDiv.className = "toast-message";
  messageDiv.textContent = message;

  // Style based on type
  const colors = {
    success: "#28a745",
    error: "#dc3545",
    warning: "#f39c12",
    info: "#17a2b8",
  };

  messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        font-size: 14px;
        z-index: 10000;
        background-color: ${colors[type] || colors.info};
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        animation: slideInToast 0.3s ease;
        max-width: 300px;
        word-wrap: break-word;
    `;

  // Add animation styles
  const style = document.createElement("style");
  style.textContent = `
        @keyframes slideInToast {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutToast {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;

  if (!document.querySelector("#toast-styles")) {
    style.id = "toast-styles";
    document.head.appendChild(style);
  }

  // Add to page
  document.body.appendChild(messageDiv);

  // Remove after 4 seconds
  setTimeout(() => {
    messageDiv.style.animation = "slideOutToast 0.3s ease";
    setTimeout(() => {
      if (messageDiv.parentNode) {
        messageDiv.remove();
      }
    }, 300);
  }, 4000);
}

// ===== INPUT FORMATTING =====
document.addEventListener("DOMContentLoaded", function () {
  // Format card number input
  const cardNumberInput = document.getElementById("card_number");
  if (cardNumberInput) {
    cardNumberInput.addEventListener("input", function () {
      // Remove all non-digit characters
      let value = this.value.replace(/\D/g, "");
      // Add spaces every 4 digits
      value = value.replace(/(\d{4})(?=\d)/g, "$1 ");
      // Limit to 19 characters (16 digits + 3 spaces)
      this.value = value.substring(0, 19);
    });
  }

  // Format expiry date input
  const expiryInput = document.getElementById("expiry");
  if (expiryInput) {
    expiryInput.addEventListener("input", function () {
      let value = this.value.replace(/\D/g, "");
      if (value.length >= 2) {
        value = value.substring(0, 2) + "/" + value.substring(2, 4);
      }
      this.value = value;
    });
  }

  // Format CVV input
  const cvvInput = document.getElementById("cvv");
  if (cvvInput) {
    cvvInput.addEventListener("input", function () {
      this.value = this.value.replace(/\D/g, "").substring(0, 4);
    });
  }
});
