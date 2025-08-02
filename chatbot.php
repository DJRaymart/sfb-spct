<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';

$auth = new Auth($conn);

// Restrict access to students and faculty only (not admin)
if (!$auth->isLoggedIn() || $auth->isAdmin()) {
    header('Location: index.php');
    exit();
}

$isLoggedIn = $auth->isLoggedIn();
$currentUser = $auth->getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="img/logo.png">
    <title>Help & Support - School Facility Reservation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .chatbot-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            width: 100%;
        }
        
        .chatbot-toggle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .chatbot-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        .chatbot-window {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: none;
            overflow: hidden;
        }
        
        .chatbot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .chatbot-header h6 {
            margin: 0;
            font-weight: 600;
        }
        
        .chatbot-close {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
        }
        
        .chatbot-body {
            height: 400px;
            display: flex;
            flex-direction: column;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        
        .message.user {
            justify-content: flex-end;
        }
        
        .message.bot {
            justify-content: flex-start;
        }
        
        .message-content {
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 15px;
            word-wrap: break-word;
        }
        
        .message.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message.bot .message-content {
            background: white;
            border: 1px solid #e9ecef;
            border-bottom-left-radius: 5px;
        }
        
        .message-time {
            font-size: 11px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .chat-input {
            padding: 15px;
            border-top: 1px solid #e9ecef;
            background: white;
        }
        
        .chat-input form {
            display: flex;
            gap: 10px;
        }
        
        .chat-input input {
            flex: 1;
            border: 1px solid #e9ecef;
            border-radius: 20px;
            padding: 10px 15px;
            outline: none;
        }
        
        .chat-input button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .chat-input button:hover {
            transform: scale(1.1);
        }
        
        .quick-actions {
            padding: 10px 15px;
            border-top: 1px solid #e9ecef;
            background: white;
        }
        
        .quick-action-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 20px;
            padding: 5px 12px;
            margin: 2px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            background: #e9ecef;
        }
        
        .email-form {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: none;
            max-width: 500px;
            width: 100%;
        }
        
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .email-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            outline: none;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn-send-email {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-send-email:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .typing-indicator {
            display: none;
            padding: 10px 15px;
            color: #6c757d;
            font-style: italic;
        }
        
        .typing-dots {
            display: inline-block;
        }
        
        .typing-dots::after {
            content: '';
            animation: typing 1.5s infinite;
        }
        
        @keyframes typing {
            0%, 20% { content: ''; }
            40% { content: '.'; }
            60% { content: '..'; }
            80%, 100% { content: '...'; }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-robot me-2"></i>
                            Help & Support Center
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="text-center mb-4">
                                    <i class="fas fa-robot fa-3x text-primary mb-3"></i>
                                    <h5>AI Assistant</h5>
                                    <p class="text-muted">Get instant help with our intelligent chatbot</p>
                                    <button class="btn btn-primary" onclick="openChatbot()">
                                        <i class="fas fa-comments me-2"></i>
                                        Start Chat
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-center mb-4">
                                    <i class="fas fa-envelope fa-3x text-success mb-3"></i>
                                    <h5>Email Support</h5>
                                    <p class="text-muted">Send us a detailed message for complex issues</p>
                                    <button class="btn btn-success" onclick="openEmailForm()">
                                        <i class="fas fa-paper-plane me-2"></i>
                                        Send Email
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h5>
                                <div class="accordion" id="faqAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                                How do I book a facility?
                                            </button>
                                        </h2>
                                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                To book a facility, go to the Dashboard and click on an available time slot. Fill in the booking form with your details, select any materials needed, and submit your request. Your booking will be reviewed by an administrator.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                                How long does approval take?
                                            </button>
                                        </h2>
                                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Approval typically takes 24-48 hours during business days. You will receive an email notification once your booking is approved or rejected.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                                Can I cancel my booking?
                                            </button>
                                        </h2>
                                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Yes, you can cancel approved bookings from your Booking History page. Pending bookings can also be cancelled. Cancellations are processed immediately.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                                What materials are available?
                                            </button>
                                        </h2>
                                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body">
                                                Materials functionality has been removed from the system.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chatbot Container -->
    <div class="chatbot-container">
        <!-- Chatbot Toggle Button -->
        <button class="chatbot-toggle" onclick="toggleChatbot()" id="chatbotToggle">
            <i class="fas fa-comments"></i>
        </button>
        
        <!-- Chatbot Window -->
        <div class="chatbot-window" id="chatbotWindow">
            <div class="chatbot-header">
                <h6><i class="fas fa-robot me-2"></i>AI Assistant</h6>
                <button class="chatbot-close" onclick="toggleChatbot()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="chatbot-body">
                <div class="chat-messages" id="chatMessages">
                    <!-- Messages will be added here -->
                </div>
                <div class="typing-indicator" id="typingIndicator">
                    <span class="typing-dots">AI Assistant is typing</span>
                </div>
                <div class="quick-actions" id="quickActions">
                    <button class="quick-action-btn" onclick="sendQuickMessage('How do I book a facility?')">Book Facility</button>
                    <button class="quick-action-btn" onclick="sendQuickMessage('How to cancel booking?')">Cancel Booking</button>

                    <button class="quick-action-btn" onclick="sendQuickMessage('Contact admin')">Contact Admin</button>
                </div>
                <div class="chat-input">
                    <form id="chatForm" onsubmit="sendMessage(event)">
                        <input type="text" id="messageInput" placeholder="Type your question..." autocomplete="off">
                        <button type="submit">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Email Form -->
        <div class="email-form" id="emailForm">
            <div class="email-header">
                <h6><i class="fas fa-envelope me-2"></i>Email Support</h6>
                <button class="chatbot-close" onclick="closeEmailForm()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="email-body">
                <form id="supportEmailForm" onsubmit="sendSupportEmail(event)">
                    <div class="form-group">
                        <label for="emailName">Your Name</label>
                        <input type="text" id="emailName" name="name" value="<?php echo $isLoggedIn ? htmlspecialchars($currentUser['full_name'] ?? '') : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="emailAddress">Email Address</label>
                        <input type="email" id="emailAddress" name="email" value="<?php echo $isLoggedIn ? htmlspecialchars($currentUser['email'] ?? '') : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="emailSubject">Subject</label>
                        <select id="emailSubject" name="subject" required>
                            <option value="">Select a topic</option>
                            <option value="Booking Issues">Booking Issues</option>
                            <option value="Technical Support">Technical Support</option>
                            <option value="Account Problems">Account Problems</option>
                            <option value="General Inquiry">General Inquiry</option>
                            <option value="Feature Request">Feature Request</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="emailMessage">Message</label>
                        <textarea id="emailMessage" name="message" placeholder="Please describe your issue or question in detail..." required></textarea>
                    </div>
                    <button type="submit" class="btn-send-email">
                        <i class="fas fa-paper-plane me-2"></i>
                        Send Email
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Chatbot functionality
        let isChatbotOpen = false;
        let isEmailFormOpen = false;

        // Initialize chatbot with welcome message
        window.onload = function() {
            addBotMessage("Hello! I'm your AI assistant. How can I help you with the School Facility Reservation System? You can ask me about booking facilities, cancelling bookings, available materials, or any other questions!");
        };

        function toggleChatbot() {
            const chatbotWindow = document.getElementById('chatbotWindow');
            const chatbotToggle = document.getElementById('chatbotToggle');
            
            if (isChatbotOpen) {
                chatbotWindow.style.display = 'none';
                chatbotToggle.style.display = 'block';
                isChatbotOpen = false;
            } else {
                chatbotWindow.style.display = 'block';
                chatbotToggle.style.display = 'none';
                isChatbotOpen = true;
                document.getElementById('messageInput').focus();
            }
        }

        function openChatbot() {
            const chatbotWindow = document.getElementById('chatbotWindow');
            const chatbotToggle = document.getElementById('chatbotToggle');
            chatbotWindow.style.display = 'block';
            chatbotToggle.style.display = 'none';
            isChatbotOpen = true;
            document.getElementById('messageInput').focus();
        }

        function openEmailForm() {
            const emailForm = document.getElementById('emailForm');
            const chatbotToggle = document.getElementById('chatbotToggle');
            emailForm.style.display = 'block';
            chatbotToggle.style.display = 'none';
            isEmailFormOpen = true;
        }

        function closeEmailForm() {
            const emailForm = document.getElementById('emailForm');
            const chatbotToggle = document.getElementById('chatbotToggle');
            emailForm.style.display = 'none';
            chatbotToggle.style.display = 'block';
            isEmailFormOpen = false;
        }

        function sendQuickMessage(message) {
            addUserMessage(message);
            processMessage(message);
        }

        function sendMessage(event) {
            event.preventDefault();
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (message) {
                addUserMessage(message);
                input.value = '';
                processMessage(message);
            }
        }

        function addUserMessage(message) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message user';
            messageDiv.innerHTML = `
                <div class="message-content">
                    ${escapeHtml(message)}
                    <div class="message-time">${new Date().toLocaleTimeString()}</div>
                </div>
            `;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        function addBotMessage(message) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message bot';
            messageDiv.innerHTML = `
                <div class="message-content">
                    ${message}
                    <div class="message-time">${new Date().toLocaleTimeString()}</div>
                </div>
            `;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        function showTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'block';
            scrollToBottom();
        }

        function hideTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'none';
        }

        function scrollToBottom() {
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function processMessage(message) {
            showTypingIndicator();
            
            // Simulate typing delay
            setTimeout(() => {
                hideTypingIndicator();
                const response = getBotResponse(message.toLowerCase());
                addBotMessage(response);
            }, 1000 + Math.random() * 2000);
        }

        function getBotResponse(message) {
            const responses = {
                'book': 'To book a facility:\n1. Go to the Dashboard\n2. Click on an available time slot\n3. Fill in the booking form\n4. Submit your request\n\nYour booking will be reviewed by an administrator within 24-48 hours.',
                
                'cancel': 'To cancel a booking:\n1. Go to Booking History\n2. Find your booking\n3. Click the cancel button\n4. Confirm cancellation\n\nCancellations are processed immediately.',
                
                'materials': 'Materials functionality has been removed from the system.',
                
                'approval': 'Booking approval typically takes 24-48 hours during business days. You will receive an email notification once your booking is approved or rejected.',
                
                'facility': 'Available facilities include:\n• IT Labs\n• AVR Rooms\n• Conference Rooms\n• Classrooms\n• Auditoriums\n\nEach facility has different equipment and capacity.',
                
                'contact': 'You can contact the administrator by:\n1. Using the email form in this chat\n2. Sending an email directly\n3. Contacting the IT department\n\nFor urgent issues, please use the email form.',
                
                'help': 'I can help you with:\n• Booking facilities\n• Cancelling bookings\n• Approval process\n• Contact information\n• General questions\n\nJust ask me anything!',
                
                'time': 'Booking times are typically:\n• Monday to Friday\n• 8:00 AM to 6:00 PM\n• Weekend bookings available with approval\n\nCheck facility availability for specific times.',
                
                'duration': 'Booking duration:\n• Minimum: 1 hour\n• Maximum: 8 hours\n• Extensions available with approval\n\nPlan your time accordingly.',
                
                'login': 'To login:\n1. Go to the login page\n2. Enter your username and password\n3. Click Login\n\nIf you forgot your password, use the "Forgot Password" link.',
                
                'register': 'To register:\n1. Click "Register" on the login page\n2. Fill in all required information\n3. Submit the form\n\nYour account will be reviewed by an administrator.',
                
                'password': 'To reset your password:\n1. Click "Forgot Password" on login page\n2. Enter your email address\n3. Check your email for reset link\n4. Follow the instructions\n\nContact admin if you have issues.',
                
                'profile': 'To update your profile:\n1. Click on your name in the top right\n2. Select "Profile"\n3. Update your information\n4. Click "Save Changes"\n\nYou can change your password there too.',
                
                'history': 'To view booking history:\n1. Click "Booking History" in the navigation\n2. View all your bookings\n3. See status and details\n4. Cancel or modify bookings\n\nThis shows all your past and current bookings.',
                
                'status': 'Booking statuses:\n• Pending: Awaiting approval\n• Approved: Confirmed booking\n• Cancelled: Cancelled by user\n• Completed: Past bookings\n\nYou can see status in Booking History.',
                
                'admin': 'Admin functions:\n• Approve/reject bookings\n• Manage facilities\n• Manage users\n• View all bookings\n• Generate reports\n\nOnly administrators have these privileges.',
                
                'email': 'To send an email to admin:\n1. Click "Send Email" button\n2. Fill in the form\n3. Select subject category\n4. Write your message\n5. Click Send\n\nYou will receive a confirmation email.',
                
                'urgent': 'For urgent issues:\n1. Use the email form for immediate response\n2. Contact the IT department directly\n3. Call the admin office\n\nUrgent requests are prioritized.',
                
                'technical': 'For technical issues:\n1. Clear your browser cache\n2. Try a different browser\n3. Check your internet connection\n4. Contact IT support\n\nMost issues are browser-related.',
                
                'mobile': 'The system works on:\n• Desktop computers\n• Laptops\n• Tablets\n• Mobile phones\n\nUse any device with internet access.',
                
                'browser': 'Recommended browsers:\n• Google Chrome\n• Mozilla Firefox\n• Microsoft Edge\n• Safari\n\nUpdate your browser for best experience.',
                
                'security': 'Security features:\n• Secure login\n• Password protection\n• Session management\n• Data encryption\n\nYour information is safe and secure.',
                
                'privacy': 'Privacy protection:\n• Your data is confidential\n• Only admins can see your bookings\n• No sharing with third parties\n\nWe protect your privacy.',
                
                'report': 'To report issues:\n1. Use the email form\n2. Include detailed description\n3. Provide screenshots if possible\n4. Mention your username\n\nWe will investigate and respond.',
                
                'feedback': 'To provide feedback:\n1. Use the email form\n2. Select "Feature Request"\n3. Describe your suggestion\n4. Include your contact info\n\nWe value your feedback!'
            };

            // Check for exact matches first
            for (const [key, response] of Object.entries(responses)) {
                if (message.includes(key)) {
                    return response;
                }
            }

            // Check for common keywords
            if (message.includes('how') || message.includes('what') || message.includes('when') || message.includes('where') || message.includes('why')) {
                return "I'm here to help! Could you please be more specific? You can ask about:\n• How to book a facility\n• How to cancel a booking\n• When bookings are approved\n• Where to find your history\n• Why a booking was rejected\n\nOr use the quick action buttons above!";
            }

            if (message.includes('hello') || message.includes('hi') || message.includes('hey')) {
                return "Hello! Welcome to the School Facility Reservation System. How can I assist you today? You can ask me about booking facilities, cancelling bookings, or any other questions!";
            }

            if (message.includes('thank')) {
                return "You're welcome! Is there anything else I can help you with?";
            }

            if (message.includes('bye') || message.includes('goodbye')) {
                return "Goodbye! Feel free to come back if you have more questions. Have a great day!";
            }

            // Default response for unrecognized messages
            return "I'm not sure I understand. Could you please rephrase your question? You can ask about:\n• Booking facilities\n• Cancelling bookings\n• Contact information\n• Technical support\n\nOr use the quick action buttons for common questions!";
        }

        function sendSupportEmail(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            // Show loading
            Swal.fire({
                title: 'Sending Email',
                text: 'Please wait while we send your message...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('send_support_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Email Sent!',
                        text: data.message,
                        confirmButtonText: 'OK'
                    });
                    closeEmailForm();
                    event.target.reset();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed to Send',
                        text: data.message,
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Unable to connect to the email server. Please check your internet connection and try again. If the problem persists, please contact the administrator.',
                    confirmButtonText: 'OK'
                });
            });
        }
    </script>
</body>
</html> 