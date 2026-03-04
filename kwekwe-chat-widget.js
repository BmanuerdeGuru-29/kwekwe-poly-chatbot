// Kwekwe Polytechnic Chat Widget JavaScript
class KwekweChatWidget {
    constructor() {
        this.isOpen = false;
        this.messages = [];
        this.isTyping = false;
        this.apiBaseUrl = 'http://localhost:8000'; // Change to production URL when deployed
        
        this.init();
    }

    init() {
        this.createWidget();
        this.attachEventListeners();
    }

    createWidget() {
        // Create chat button
        const chatButton = document.createElement('button');
        chatButton.className = 'kwekwe-chat-button';
        chatButton.innerHTML = '<i class="fas fa-comments"></i>';
        chatButton.title = 'Chat with Kwekwe Polytechnic Assistant';
        chatButton.id = 'kwekwe-chat-button';

        // Create chat container
        const chatContainer = document.createElement('div');
        chatContainer.className = 'kwekwe-chat-container';
        chatContainer.id = 'kwekwe-chat-container';
        chatContainer.innerHTML = `
            <div class="kwekwe-chat-header">
                <div class="kwekwe-chat-title">
                    <div class="kwekwe-chat-logo"></div>
                    <div class="kwekwe-chat-status">
                        <div class="kwekwe-chat-name">Kwekwe Assistant</div>
                        <div class="kwekwe-online-indicator">
                            <div class="kwekwe-status-dot"></div>
                            <span>Online</span>
                        </div>
                    </div>
                </div>
                <button class="kwekwe-close-button" id="kwekwe-close-button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="kwekwe-chat-messages" id="kwekwe-chat-messages">
                <div class="kwekwe-welcome-message">
                    <div class="kwekwe-welcome-logo">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="kwekwe-welcome-text">
                        Welcome to Kwekwe Polytechnic! I'm here to help you with information about our courses, fees, admissions, and more.
                    </div>
                    <div class="kwekwe-suggestions">
                        <div class="kwekwe-suggestion-chip" data-suggestion="What are the engineering requirements?">
                            Engineering Requirements
                        </div>
                        <div class="kwekwe-suggestion-chip" data-suggestion="Tell me about Applied Sciences programs">
                            Applied Sciences
                        </div>
                        <div class="kwekwe-suggestion-chip" data-suggestion="What B-Tech programs are available?">
                            B-Tech Programs
                        </div>
                        <div class="kwekwe-suggestion-chip" data-suggestion="How can I pay my fees?">
                            Payment Methods
                        </div>
                        <div class="kwekwe-suggestion-chip" data-suggestion="What about HEXCO results?">
                            HEXCO Results
                        </div>
                        <div class="kwekwe-suggestion-chip" data-suggestion="How can I contact Kwekwe Polytechnic?">
                            Contact Information
                        </div>
                    </div>
                </div>
                
                <div class="kwekwe-typing-indicator" id="kwekwe-typing-indicator">
                    <div class="kwekwe-typing-dots">
                        <div class="kwekwe-typing-dot"></div>
                        <div class="kwekwe-typing-dot"></div>
                        <div class="kwekwe-typing-dot"></div>
                    </div>
                </div>
            </div>
            
            <div class="kwekwe-chat-input">
                <div class="kwekwe-input-wrapper">
                    <input type="text" id="kwekwe-chat-input" placeholder="Ask about Kwekwe Polytechnic..." disabled>
                </div>
                <button class="kwekwe-send-button" id="kwekwe-send-button" disabled>
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        `;

        // Add to page
        document.body.appendChild(chatButton);
        document.body.appendChild(chatContainer);
    }

    attachEventListeners() {
        // Chat button click
        document.getElementById('kwekwe-chat-button').addEventListener('click', () => {
            this.openChat();
        });

        // Close button click
        document.getElementById('kwekwe-close-button').addEventListener('click', () => {
            this.closeChat();
        });

        // Send button click
        document.getElementById('kwekwe-send-button').addEventListener('click', () => {
            this.sendMessage();
        });

        // Enter key press
        document.getElementById('kwekwe-chat-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.sendMessage();
            }
        });

        // Suggestion chips click
        document.querySelectorAll('.kwekwe-suggestion-chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                const suggestion = e.target.getAttribute('data-suggestion');
                document.getElementById('kwekwe-chat-input').value = suggestion;
                this.sendMessage();
            });
        });
    }

    openChat() {
        this.isOpen = true;
        document.getElementById('kwekwe-chat-container').style.display = 'flex';
        document.getElementById('kwekwe-chat-button').style.display = 'none';
        document.getElementById('kwekwe-chat-input').disabled = false;
        document.getElementById('kwekwe-send-button').disabled = false;
        document.getElementById('kwekwe-chat-input').focus();
    }

    closeChat() {
        this.isOpen = false;
        document.getElementById('kwekwe-chat-container').style.display = 'none';
        document.getElementById('kwekwe-chat-button').style.display = 'flex';
    }

    async sendMessage() {
        const input = document.getElementById('kwekwe-chat-input');
        const message = input.value.trim();
        
        if (!message) return;

        // Add user message
        this.addMessage(message, 'user');
        input.value = '';
        this.showTypingIndicator();

        try {
            const response = await fetch(`${this.apiBaseUrl}/chat/query`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    use_tools: false
                })
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            this.addMessage(data.response, 'assistant');
        } catch (error) {
            console.error('Chat API Error:', error);
            this.addMessage('Sorry, I encountered an error. Please try again later.', 'assistant');
        } finally {
            this.hideTypingIndicator();
        }
    }

    addMessage(content, role) {
        const messagesContainer = document.getElementById('kwekwe-chat-messages');
        const welcomeMessage = messagesContainer.querySelector('.kwekwe-welcome-message');
        
        // Hide welcome message if it's the first message
        if (welcomeMessage && this.messages.length === 0) {
            welcomeMessage.style.display = 'none';
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `kwekwe-message ${role}`;
        messageDiv.innerHTML = `
            <div class="kwekwe-message-avatar">
                <i class="fas fa-${role === 'user' ? 'user' : 'robot'}"></i>
            </div>
            <div class="kwekwe-message-content">${content}</div>
        `;

        // Insert before typing indicator
        const typingIndicator = document.getElementById('kwekwe-typing-indicator');
        messagesContainer.insertBefore(messageDiv, typingIndicator);

        this.messages.push({ role, content, timestamp: new Date().toISOString() });
        this.scrollToBottom();
    }

    showTypingIndicator() {
        this.isTyping = true;
        document.getElementById('kwekwe-typing-indicator').style.display = 'block';
        document.getElementById('kwekwe-chat-input').disabled = true;
        document.getElementById('kwekwe-send-button').disabled = true;
        this.scrollToBottom();
    }

    hideTypingIndicator() {
        this.isTyping = false;
        document.getElementById('kwekwe-typing-indicator').style.display = 'none';
        document.getElementById('kwekwe-chat-input').disabled = false;
        document.getElementById('kwekwe-send-button').disabled = false;
        document.getElementById('kwekwe-chat-input').focus();
    }

    scrollToBottom() {
        const messagesContainer = document.getElementById('kwekwe-chat-messages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Public method to update API base URL
    setApiBaseUrl(url) {
        this.apiBaseUrl = url;
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if Font Awesome is loaded, if not load it
    if (!document.querySelector('link[href*="font-awesome"]')) {
        const fontAwesome = document.createElement('link');
        fontAwesome.rel = 'stylesheet';
        fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        document.head.appendChild(fontAwesome);
    }
    
    // Initialize chat widget
    window.kwekweChatWidget = new KwekweChatWidget();
});

// Export for manual initialization if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = KwekweChatWidget;
}
