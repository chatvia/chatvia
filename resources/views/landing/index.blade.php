@extends('layouts.landing')

@section('content')
<div x-data="{
    mobileMenuOpen: false,
    pricingMonthly: true,
    faqOpen: null,
    formData: {
        name: '',
        email: '',
        feature: ''
    },
    submitFeatureRequest() {
        alert('Thank you for your feature request! We\'ll review it and get back to you soon.');
        this.formData = { name: '', email: '', feature: '' };
    }
}">
    <!-- Navigation -->
    <nav class="fixed w-full bg-white/95 backdrop-blur-md z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center">
                    <a href="/" class="text-2xl font-bold gradient-text">ChatVia</a>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#features" class="text-gray-700 hover:text-purple-600 transition font-medium">Features</a>
                    <a href="#how-it-works" class="text-gray-700 hover:text-purple-600 transition font-medium">How It Works</a>
                    <a href="#pricing" class="text-gray-700 hover:text-purple-600 transition font-medium">Pricing</a>
                    <a href="#faq" class="text-gray-700 hover:text-purple-600 transition font-medium">FAQ</a>
                    <a href="/login" class="px-4 py-2 text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition font-medium">Get Started</a>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path x-show="mobileMenuOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div x-show="mobileMenuOpen" x-cloak class="md:hidden bg-white border-t">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="#features" @click="mobileMenuOpen = false" class="block px-3 py-2 text-gray-700 hover:text-purple-600">Features</a>
                <a href="#how-it-works" @click="mobileMenuOpen = false" class="block px-3 py-2 text-gray-700 hover:text-purple-600">How It Works</a>
                <a href="#pricing" @click="mobileMenuOpen = false" class="block px-3 py-2 text-gray-700 hover:text-purple-600">Pricing</a>
                <a href="#faq" @click="mobileMenuOpen = false" class="block px-3 py-2 text-gray-700 hover:text-purple-600">FAQ</a>
                <a href="/login" class="block px-3 py-2 text-white bg-purple-600 hover:bg-purple-700 rounded-lg text-center">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="pt-24 pb-12 md:pt-32 md:pb-20 bg-gradient-to-b from-gray-50 to-white overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="inline-flex items-center px-4 py-1.5 mb-6 text-sm font-medium rounded-full bg-purple-100 text-purple-700">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                    Start for FREE
                </div>
                <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold text-gray-900 mb-6 leading-tight">
                    Create your own,<br>
                    <span class="gradient-text">ChatGPT</span>
                </h1>
                <p class="text-xl md:text-2xl text-gray-600 mb-8 max-w-4xl mx-auto leading-relaxed">
                    Experience the power of advanced AI conversations with a beautiful, intuitive interface.
                    Get instant responses, creative assistance, and intelligent insights.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-12">
                    <a href="/register" class="px-8 py-4 text-white bg-purple-600 hover:bg-purple-700 rounded-lg font-semibold transition transform hover:scale-105 shadow-lg hover:shadow-xl">
                        Start Free 14-Day Trial
                    </a>
                    <a href="#how-it-works" class="px-8 py-4 text-purple-600 bg-white hover:bg-gray-50 rounded-lg font-semibold border-2 border-purple-200 transition">
                        Watch Demo
                    </a>
                </div>

                <!-- Trust Badges -->
                <div class="flex flex-wrap justify-center items-center gap-8 text-gray-500">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="font-medium">SOC 2 Compliant</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="font-medium">GDPR Compliant</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="font-medium">99.9% Uptime</span>
                    </div>
                </div>
            </div>


            <script>
                function chatDemo() {
                    return {
                        messages: [],
                        isTyping: false,
                        typingText: '',
                        isTypingMessage: false,
                        conversation: [
                            { type: 'user', text: 'Can you help me write a professional email?', time: '2:34 PM' },
                            { type: 'assistant', text: 'Of course! I\'d be happy to help you write a professional email. To get started, could you tell me:<br><br>• Who is the recipient?<br>• What\'s the purpose of the email?<br>• Any key points to include?', time: '2:34 PM' },
                            { type: 'user', text: 'It\'s for my manager about requesting time off next month', time: '2:35 PM' },
                            { type: 'assistant', text: 'I\'ll help you draft a professional time-off request. Here\'s a template:<br><br>"Subject: Time Off Request - [Dates]<br><br>Dear [Manager\'s Name],<br><br>I would like to request time off from [start date] to [end date]. I will ensure all my current projects are completed or properly handed over before my leave.<br><br>Please let me know if you need any additional information.<br><br>Thank you for considering my request."<br><br>Would you like me to customize this further?', time: '2:35 PM' }
                        ],
                        currentIndex: 0,

                        init() {
                            this.startConversation();
                        },

                        startConversation() {
                            this.playNextMessage();
                        },

                        async typeMessage(text) {
                            this.typingText = '';
                            this.isTypingMessage = true;

                            // Type each character with a slight delay
                            for (let i = 0; i < text.length; i++) {
                                this.typingText += text[i];
                                await new Promise(resolve => setTimeout(resolve, 15 + Math.random() * 20)); // Faster typing speed
                            }

                            // Pause after typing is complete
                            await new Promise(resolve => setTimeout(resolve, 200));

                            // Clear the input field
                            this.typingText = '';
                            this.isTypingMessage = false;
                        },

                        async playNextMessage() {
                            if (this.currentIndex < this.conversation.length) {
                                const message = this.conversation[this.currentIndex];

                                if (message.type === 'user') {
                                    // Type the user message first
                                    await this.typeMessage(message.text);
                                    // Then add it to the chat
                                    this.addMessage(message);
                                    this.currentIndex++;
                                    setTimeout(() => this.playNextMessage(), 1000);
                                } else if (message.type === 'assistant') {
                                    this.isTyping = true;
                                    setTimeout(() => {
                                        this.isTyping = false;
                                        this.addMessage(message);
                                        this.currentIndex++;
                                        setTimeout(() => this.playNextMessage(), 1000);
                                    }, 1200);
                                }
                            } else {
                                // Restart the conversation
                                setTimeout(() => {
                                    this.messages = [];
                                    this.currentIndex = 0;
                                    this.startConversation();
                                }, 3000);
                            }
                        },

                        addMessage(message) {
                            const newMessage = { ...message, show: false };
                            this.messages.push(newMessage);

                            // Trigger animation
                            this.$nextTick(() => {
                                this.messages[this.messages.length - 1].show = true;

                                // Scroll to bottom
                                const container = document.getElementById('chat-container');
                                setTimeout(() => {
                                    container.scrollTop = container.scrollHeight;
                                }, 100);
                            });
                        }
                    };
                }
            </script>
        </div>
    </section>

    <!-- Supported LLMs Section -->
    <section class="py-16 bg-gradient-to-b from-white to-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <span class="text-purple-600 font-semibold text-sm uppercase tracking-wide">POWERED BY</span>
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4 mt-2">
                    Leading AI Models
                </h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Access the most advanced language models in one unified platform - GPT-4, Claude 3, Gemini Pro, and more
                </p>
            </div>

            <!-- LLM Models - Compact Grid -->
            <div class="max-w-4xl mx-auto">
                <div class="flex flex-wrap items-center justify-center gap-6 md:gap-8">
                    <!-- Row 1 -->
                    <div class="group cursor-pointer">
                        <div class="w-16 h-16 md:w-20 md:h-20 bg-white rounded-full shadow-md flex items-center justify-center transform transition-all duration-300 hover:scale-110 hover:shadow-lg border border-gray-100">
                            <img src="{{ asset('images/llms/gpt.svg') }}" alt="GPT-4" class="w-10 h-10 md:w-12 md:h-12 object-contain">
                        </div>
                        <p class="text-center text-sm text-gray-600 mt-2 font-medium">GPT-4</p>
                    </div>

                    <div class="group cursor-pointer">
                        <div class="w-16 h-16 md:w-20 md:h-20 bg-white rounded-full shadow-md flex items-center justify-center transform transition-all duration-300 hover:scale-110 hover:shadow-lg border border-gray-100">
                            <img src="{{ asset('images/llms/claude.svg') }}" alt="Claude 3" class="w-10 h-10 md:w-12 md:h-12 object-contain">
                        </div>
                        <p class="text-center text-sm text-gray-600 mt-2 font-medium">Claude 3</p>
                    </div>

                    <div class="group cursor-pointer">
                        <div class="w-16 h-16 md:w-20 md:h-20 bg-white rounded-full shadow-md flex items-center justify-center transform transition-all duration-300 hover:scale-110 hover:shadow-lg border border-gray-100">
                            <img src="{{ asset('images/llms/gemini.svg') }}" alt="Gemini Pro" class="w-10 h-10 md:w-12 md:h-12 object-contain">
                        </div>
                        <p class="text-center text-sm text-gray-600 mt-2 font-medium">Gemini Pro</p>
                    </div>

                    <div class="group cursor-pointer">
                        <div class="w-16 h-16 md:w-20 md:h-20 bg-white rounded-full shadow-md flex items-center justify-center transform transition-all duration-300 hover:scale-110 hover:shadow-lg border border-gray-100">
                            <img src="{{ asset('images/llms/llama.svg') }}" alt="LLaMA 3" class="w-10 h-10 md:w-12 md:h-12 object-contain">
                        </div>
                        <p class="text-center text-sm text-gray-600 mt-2 font-medium">LLaMA 3</p>
                    </div>

                    <div class="group cursor-pointer">
                        <div class="w-16 h-16 md:w-20 md:h-20 bg-white rounded-full shadow-md flex items-center justify-center transform transition-all duration-300 hover:scale-110 hover:shadow-lg border border-gray-100">
                            <img src="{{ asset('images/llms/mistral.svg') }}" alt="Mistral AI" class="w-10 h-10 md:w-12 md:h-12 object-contain">
                        </div>
                        <p class="text-center text-sm text-gray-600 mt-2 font-medium">Mistral AI</p>
                    </div>

                    <!-- Row 2 -->
                    <div class="group cursor-pointer">
                        <div class="w-16 h-16 md:w-20 md:h-20 bg-white rounded-full shadow-md flex items-center justify-center transform transition-all duration-300 hover:scale-110 hover:shadow-lg border border-gray-100">
                            <img src="{{ asset('images/llms/deepseek.svg') }}" alt="DeepSeek" class="w-10 h-10 md:w-12 md:h-12 object-contain">
                        </div>
                        <p class="text-center text-sm text-gray-600 mt-2 font-medium">DeepSeek</p>
                    </div>

                    <div class="group cursor-pointer">
                        <div class="w-16 h-16 md:w-20 md:h-20 bg-white rounded-full shadow-md flex items-center justify-center transform transition-all duration-300 hover:scale-110 hover:shadow-lg border border-gray-100">
                            <img src="{{ asset('images/llms/qwen.svg') }}" alt="Qwen" class="w-10 h-10 md:w-12 md:h-12 object-contain">
                        </div>
                        <p class="text-center text-sm text-gray-600 mt-2 font-medium">Qwen</p>
                    </div>

                    <div class="group cursor-pointer">
                        <div class="w-16 h-16 md:w-20 md:h-20 bg-white rounded-full shadow-md flex items-center justify-center transform transition-all duration-300 hover:scale-110 hover:shadow-lg border border-gray-100">
                            <img src="{{ asset('images/llms/grok.svg') }}" alt="Grok" class="w-10 h-10 md:w-12 md:h-12 object-contain">
                        </div>
                        <p class="text-center text-sm text-gray-600 mt-2 font-medium">Grok</p>
                    </div>

                    <div class="group cursor-pointer">
                        <div class="w-16 h-16 md:w-20 md:h-20 bg-white rounded-full shadow-md flex items-center justify-center transform transition-all duration-300 hover:scale-110 hover:shadow-lg border border-gray-100">
                            <img src="{{ asset('images/llms/microsoft.svg') }}" alt="Copilot" class="w-10 h-10 md:w-12 md:h-12 object-contain">
                        </div>
                        <p class="text-center text-sm text-gray-600 mt-2 font-medium">Copilot</p>
                    </div>
                </div>

                <!-- Optional: Scrolling banner style -->
                <div class="mt-12 text-center">
                    <p class="text-sm text-gray-500">
                        Plus Perplexity, Cohere, Anthropic's latest models, and many more coming soon...
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-purple-600 font-semibold text-sm uppercase tracking-wide">SIMPLE PROCESS</span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4 mt-2">
                    Three Steps to Your AI Agent
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Get your intelligent AI assistant up and running in minutes, not months
                </p>
            </div>

            <div class="relative">
                <!-- Desktop curved arrows -->
                <svg class="hidden lg:block absolute top-24 left-1/4 w-1/4 h-20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 40 Q 120 20, 220 40" stroke="url(#gradient1)" stroke-width="3" fill="none" stroke-dasharray="5,5" opacity="0.3"/>
                    <defs>
                        <linearGradient id="gradient1" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" style="stop-color:#a855f7;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#6366f1;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                </svg>

                <svg class="hidden lg:block absolute top-24 right-1/4 w-1/4 h-20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 40 Q 120 20, 220 40" stroke="url(#gradient2)" stroke-width="3" fill="none" stroke-dasharray="5,5" opacity="0.3"/>
                    <defs>
                        <linearGradient id="gradient2" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" style="stop-color:#6366f1;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#ec4899;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                </svg>

                <div class="grid md:grid-cols-3 gap-8 relative z-10">
                    <!-- Step 1 -->
                    <div class="group relative">
                        <div class="bg-white rounded-2xl shadow-xl p-8 hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 border border-gray-100">
                            <div class="absolute -top-6 left-8">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-lg">
                                    1
                                </div>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-100 to-purple-200 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">Create Your Agent</h3>
                            <p class="text-gray-600 leading-relaxed">Choose a name, set the personality, and configure how your AI agent should behave and respond to queries.</p>
                            <div class="mt-6 flex items-center text-purple-600 font-medium group-hover:translate-x-2 transition-transform">
                                <span>Customize behavior</span>
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="group relative">
                        <div class="bg-white rounded-2xl shadow-xl p-8 hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 border border-gray-100">
                            <div class="absolute -top-6 left-8">
                                <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-lg">
                                    2
                                </div>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-100 to-indigo-200 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">Upload Your Files</h3>
                            <p class="text-gray-600 leading-relaxed">Drag and drop your PDFs, documents, spreadsheets, audio, or video files. We'll process and index everything automatically.</p>
                            <div class="mt-6 flex items-center text-indigo-600 font-medium group-hover:translate-x-2 transition-transform">
                                <span>Support all formats</span>
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="group relative">
                        <div class="bg-white rounded-2xl shadow-xl p-8 hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 border border-gray-100">
                            <div class="absolute -top-6 left-8">
                                <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-pink-600 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-lg">
                                    3
                                </div>
                            </div>
                            <div class="w-16 h-16 bg-gradient-to-br from-pink-100 to-pink-200 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                <svg class="w-8 h-8 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">Start Using It</h3>
                            <p class="text-gray-600 leading-relaxed">Your AI agent is ready! Embed it on your website, integrate via API, or use it directly in our platform.</p>
                            <div class="mt-6 flex items-center text-pink-600 font-medium group-hover:translate-x-2 transition-transform">
                                <span>Deploy anywhere</span>
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-purple-600 font-semibold text-sm uppercase tracking-wide">POWERFUL FEATURES</span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4 mt-2">
                    Everything You Need to Build Intelligent AI Agents
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    From document processing to real-time conversations, we've got you covered
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card p-8 bg-white border border-gray-200 rounded-2xl hover:shadow-xl hover:border-purple-200 transition-all duration-300">
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Multi-Format Document Processing</h3>
                    <p class="text-gray-600 mb-4">Upload PDFs, Word documents, Excel spreadsheets, PowerPoints, and plain text files. Our AI extracts and understands content from any document format.</p>
                    <ul class="text-sm text-gray-500 space-y-2">
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>OCR for scanned documents</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>Table and chart extraction</li>
                    </ul>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card p-6 bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl hover:shadow-lg">
                    <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Custom Training</h3>
                    <p class="text-gray-600">Train your AI agents on your own data and knowledge base for accurate, contextual responses.</p>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card p-6 bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl hover:shadow-lg">
                    <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Lightning Fast</h3>
                    <p class="text-gray-600">Get responses in milliseconds. Our infrastructure ensures your agents are always responsive.</p>
                </div>

                <!-- Feature 4 -->
                <div class="feature-card p-6 bg-gradient-to-br from-pink-50 to-rose-50 rounded-xl hover:shadow-lg">
                    <div class="w-12 h-12 bg-pink-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Multi-Channel</h3>
                    <p class="text-gray-600">Deploy your agents across websites, mobile apps, messaging platforms, and more.</p>
                </div>

                <!-- Feature 5 -->
                <div class="feature-card p-6 bg-gradient-to-br from-yellow-50 to-amber-50 rounded-xl hover:shadow-lg">
                    <div class="w-12 h-12 bg-yellow-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Analytics & Insights</h3>
                    <p class="text-gray-600">Track performance, user interactions, and gain insights to improve your AI agents.</p>
                </div>

                <!-- Feature 6 -->
                <div class="feature-card p-6 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl hover:shadow-lg">
                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Enterprise Security</h3>
                    <p class="text-gray-600">Bank-level encryption, SOC 2 compliance, and advanced security features to protect your data.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Simple, Transparent Pricing
                </h2>
                <p class="text-xl text-gray-600 mb-8">Choose the plan that fits your needs</p>

                <!-- Pricing Toggle -->
                <div class="flex items-center justify-center space-x-4">
                    <span :class="pricingMonthly ? 'text-gray-900' : 'text-gray-500'" class="font-medium">Monthly</span>
                    <button @click="pricingMonthly = !pricingMonthly" type="button" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2" :class="pricingMonthly ? 'bg-gray-200' : 'bg-purple-600'">
                        <span :class="pricingMonthly ? 'translate-x-1' : 'translate-x-6'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm"></span>
                    </button>
                    <span :class="!pricingMonthly ? 'text-gray-900' : 'text-gray-500'" class="font-medium">
                        Yearly <span class="text-green-600 text-sm">(Save 20%)</span>
                    </span>
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <!-- Starter Plan -->
                <div class="bg-white rounded-2xl shadow-lg p-8 relative hover:shadow-xl transition">
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Starter</h3>
                    <div class="mb-6">
                        <span class="text-4xl font-bold text-gray-900">
                            <span x-show="pricingMonthly">$19</span>
                            <span x-show="!pricingMonthly">$15</span>
                        </span>
                        <span class="text-gray-600">/month</span>
                    </div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">Up to 3 AI agents</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">5,000 messages/month</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">Basic analytics</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">Email support</span>
                        </li>
                    </ul>
                    <button class="w-full py-3 px-6 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition">
                        Get Started
                    </button>
                </div>

                <!-- Professional Plan -->
                <div class="bg-white rounded-2xl shadow-xl p-8 relative hover:shadow-2xl transition transform scale-105">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 bg-purple-600 text-white px-4 py-1 rounded-full text-sm font-semibold">
                        Most Popular
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Professional</h3>
                    <div class="mb-6">
                        <span class="text-4xl font-bold text-gray-900">
                            <span x-show="pricingMonthly">$49</span>
                            <span x-show="!pricingMonthly">$39</span>
                        </span>
                        <span class="text-gray-600">/month</span>
                    </div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">Up to 10 AI agents</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">50,000 messages/month</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">Advanced analytics</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">Custom integrations</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">Priority support</span>
                        </li>
                    </ul>
                    <button class="w-full py-3 px-6 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                        Get Started
                    </button>
                </div>

                <!-- Enterprise Plan -->
                <div class="bg-white rounded-2xl shadow-lg p-8 relative hover:shadow-xl transition">
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Enterprise</h3>
                    <div class="mb-6">
                        <span class="text-4xl font-bold text-gray-900">Custom</span>
                    </div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">Unlimited AI agents</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">Unlimited messages</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">White-label options</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">Dedicated support</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-gray-600">SLA guarantee</span>
                        </li>
                    </ul>
                    <button class="w-full py-3 px-6 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition">
                        Contact Sales
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-20 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Frequently Asked Questions
                </h2>
                <p class="text-xl text-gray-600">Got questions? We've got answers</p>
            </div>

            <div class="space-y-4">
                <!-- FAQ Item 1 -->
                <div class="border border-gray-200 rounded-lg">
                    <button @click="faqOpen = faqOpen === 1 ? null : 1" class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-gray-50 transition">
                        <span class="font-semibold text-gray-900">What is ChatVia and how does it work?</span>
                        <svg :class="{'rotate-180': faqOpen === 1}" class="w-5 h-5 text-gray-500 transform transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="faqOpen === 1" x-cloak class="px-6 pb-4">
                        <p class="text-gray-600">ChatVia is a platform that allows you to create custom AI agents similar to ChatGPT. You can train these agents on your specific data, customize their behavior, and deploy them across various channels without any coding knowledge.</p>
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="border border-gray-200 rounded-lg">
                    <button @click="faqOpen = faqOpen === 2 ? null : 2" class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-gray-50 transition">
                        <span class="font-semibold text-gray-900">Do I need coding skills to use ChatVia?</span>
                        <svg :class="{'rotate-180': faqOpen === 2}" class="w-5 h-5 text-gray-500 transform transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="faqOpen === 2" x-cloak class="px-6 pb-4">
                        <p class="text-gray-600">No coding skills are required! ChatVia features a no-code builder that lets you create sophisticated AI agents through a simple drag-and-drop interface. For advanced users, we also provide API access for custom integrations.</p>
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="border border-gray-200 rounded-lg">
                    <button @click="faqOpen = faqOpen === 3 ? null : 3" class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-gray-50 transition">
                        <span class="font-semibold text-gray-900">Can I integrate ChatVia with my existing tools?</span>
                        <svg :class="{'rotate-180': faqOpen === 3}" class="w-5 h-5 text-gray-500 transform transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="faqOpen === 3" x-cloak class="px-6 pb-4">
                        <p class="text-gray-600">Yes! ChatVia supports integrations with popular platforms like Slack, Discord, WhatsApp, websites, and mobile apps. We also provide webhooks and APIs for custom integrations with your existing tools and workflows.</p>
                    </div>
                </div>

                <!-- FAQ Item 4 -->
                <div class="border border-gray-200 rounded-lg">
                    <button @click="faqOpen = faqOpen === 4 ? null : 4" class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-gray-50 transition">
                        <span class="font-semibold text-gray-900">How secure is my data on ChatVia?</span>
                        <svg :class="{'rotate-180': faqOpen === 4}" class="w-5 h-5 text-gray-500 transform transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="faqOpen === 4" x-cloak class="px-6 pb-4">
                        <p class="text-gray-600">We take security seriously. All data is encrypted at rest and in transit using bank-level encryption. We're SOC 2 compliant and follow industry best practices for data security. Your data is never used to train models for other customers.</p>
                    </div>
                </div>

                <!-- FAQ Item 5 -->
                <div class="border border-gray-200 rounded-lg">
                    <button @click="faqOpen = faqOpen === 5 ? null : 5" class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-gray-50 transition">
                        <span class="font-semibold text-gray-900">Can I switch plans or cancel anytime?</span>
                        <svg :class="{'rotate-180': faqOpen === 5}" class="w-5 h-5 text-gray-500 transform transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="faqOpen === 5" x-cloak class="px-6 pb-4">
                        <p class="text-gray-600">Absolutely! You can upgrade, downgrade, or cancel your subscription at any time. No long-term contracts or hidden fees. If you cancel, you'll continue to have access until the end of your billing period.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Request Feature Section -->
    <section id="request-feature" class="py-20 bg-gradient-to-br from-purple-50 to-indigo-100">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12">
                <div class="text-center mb-8">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        Request a Feature
                    </h2>
                    <p class="text-xl text-gray-600">Help us make ChatVia better by suggesting new features</p>
                </div>

                <form @submit.prevent="submitFeatureRequest" class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Your Name</label>
                        <input type="text" id="name" x-model="formData.name" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="email" x-model="formData.email" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <div>
                        <label for="feature" class="block text-sm font-medium text-gray-700 mb-2">Feature Description</label>
                        <textarea id="feature" x-model="formData.feature" rows="5" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                  placeholder="Describe the feature you'd like to see..."></textarea>
                    </div>

                    <button type="submit" class="w-full py-3 px-6 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-semibold">
                        Submit Feature Request
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-2xl font-bold mb-4">ChatVia</h3>
                    <p class="text-gray-400">Building the future of conversational AI, one agent at a time.</p>
                </div>

                <div>
                    <h4 class="font-semibold mb-4">Product</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#features" class="hover:text-white transition">Features</a></li>
                        <li><a href="#pricing" class="hover:text-white transition">Pricing</a></li>
                        <li><a href="#" class="hover:text-white transition">Documentation</a></li>
                        <li><a href="#" class="hover:text-white transition">API Reference</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-semibold mb-4">Company</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white transition">About Us</a></li>
                        <li><a href="#" class="hover:text-white transition">Blog</a></li>
                        <li><a href="#" class="hover:text-white transition">Careers</a></li>
                        <li><a href="#" class="hover:text-white transition">Contact</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-white transition">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-white transition">Cookie Policy</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; {{ date('Y') }} ChatVia. All rights reserved.</p>
            </div>
        </div>
    </footer>
</div>
@endsection
