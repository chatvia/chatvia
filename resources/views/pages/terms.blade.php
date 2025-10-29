<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - ChatVia | AI Chatbot Platform</title>
    
    @section('description', 'Read ChatVia\'s Terms of Service. Learn about our AI chatbot platform policies, user agreements, data processing terms, and service conditions.')
    @section('og_title', 'Terms of Service - ChatVia')
    @section('og_description', 'ChatVia Terms of Service - Understand your rights and responsibilities when using our AI-powered chatbot platform.')
    
    @vite(['resources/css/landing.css'])
    @include('partials.cover')
</head>
<body class="bg-gray-50 text-gray-900">
<div class="min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">ChatVia</h1>
                </div>
                <a href="{{ route('home') }}" class="text-sm text-gray-600 hover:text-gray-900">← Back to Home</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-sm border p-8 space-y-8">
            <!-- Title -->
            <div class="text-center border-b pb-6">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Terms of Service</h1>
                <p class="text-gray-600">Last updated: 10 Aug 2025</p>
            </div>

            <!-- Introduction -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">1. Acceptance of Terms</h2>
                <p class="text-gray-700 leading-relaxed">
                    Welcome to ChatVia ("we," "our," or "us"). These Terms of Service ("Terms") govern your use of ChatVia's Retrieval-Augmented Generation (RAG) application and AI-powered chat agents ("Service"). By creating an account or using our Service, you agree to be bound by these Terms.
                </p>
                <p class="text-gray-700 leading-relaxed">
                    If you do not agree to these Terms, please do not use our Service. We may update these Terms from time to time, and your continued use of the Service constitutes acceptance of any changes.
                </p>
            </section>

            <!-- Service Description -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">2. Service Description</h2>

                <h3 class="text-xl font-medium text-gray-800">2.1 ChatVia Platform</h3>
                <p class="text-gray-700 leading-relaxed">
                    ChatVia is an AI-powered platform that enables you to create AI agents by uploading documents and leveraging advanced Retrieval-Augmented Generation (RAG) technology. Our Service includes:
                </p>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li>Document ingestion and processing capabilities</li>
                    <li>AI agent creation and management</li>
                    <li>Chat interfaces for interacting with your agents</li>
                    <li>Integration with third-party AI providers</li>
                    <li>Data storage and management tools</li>
                </ul>

                <h3 class="text-xl font-medium text-gray-800">2.2 AI Technology</h3>
                <p class="text-gray-700 leading-relaxed">
                    Our Service utilizes RAG technology to process your documents and provide intelligent responses. We partner with various AI providers including Cohere, Mistral AI, OpenAI, and Google AI to deliver these capabilities.
                </p>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-semibold text-blue-800 mb-2">Important:</h4>
                    <p class="text-blue-700 text-sm">
                        ChatVia does not train AI models on your data. We use RAG technology to find relevant information from your documents and send it to your chosen AI model for processing.
                    </p>
                </div>
            </section>

            <!-- Account Terms -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">3. Account Terms</h2>

                <h3 class="text-xl font-medium text-gray-800">3.1 Account Creation</h3>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li>You must provide accurate and complete information when creating an account</li>
                    <li>You are responsible for maintaining the security of your account credentials</li>
                    <li>You must be at least 13 years old to use our Service</li>
                    <li>One person or legal entity may maintain only one account</li>
                </ul>

                <h3 class="text-xl font-medium text-gray-800">3.2 Account Responsibilities</h3>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li>You are responsible for all activities that occur under your account</li>
                    <li>You must notify us immediately of any unauthorized use of your account</li>
                    <li>You must keep your contact information current and accurate</li>
                    <li>You may not share your account with others or allow others to access your account</li>
                </ul>
            </section>

            <!-- Acceptable Use -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">4. Acceptable Use Policy</h2>

                <h3 class="text-xl font-medium text-gray-800">4.1 Permitted Uses</h3>
                <p class="text-gray-700 leading-relaxed">
                    You may use ChatVia for legitimate business, educational, and personal purposes, including:
                </p>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li>Creating knowledge bases from your documents</li>
                    <li>Building AI assistants for customer support</li>
                    <li>Developing educational content and training materials</li>
                    <li>Research and analysis of your own data</li>
                </ul>

                <h3 class="text-xl font-medium text-gray-800">4.2 Prohibited Uses</h3>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-800 font-semibold mb-2">You agree NOT to use ChatVia for:</p>
                    <ul class="list-disc list-inside text-red-700 space-y-1 ml-4 text-sm">
                        <li>Uploading copyrighted content without proper authorization</li>
                        <li>Creating or distributing harmful, offensive, or illegal content</li>
                        <li>Attempting to reverse engineer or exploit our systems</li>
                        <li>Violating any applicable laws or regulations</li>
                        <li>Uploading malicious software or code</li>
                        <li>Interfering with or disrupting our services</li>
                        <li>Creating agents that impersonate others or provide medical/legal advice</li>
                        <li>Processing personal data without proper consent</li>
                    </ul>
                </div>
            </section>

            <!-- Data and Content -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">5. Your Data and Content</h2>

                <h3 class="text-xl font-medium text-gray-800">5.1 Your Content Ownership</h3>
                <p class="text-gray-700 leading-relaxed">
                    You retain ownership of all documents, data, and content you upload to ChatVia. We do not claim ownership of your content and will not use it for training AI models or for any purpose other than providing our Service to you.
                </p>

                <h3 class="text-xl font-medium text-gray-800">5.2 Content License</h3>
                <p class="text-gray-700 leading-relaxed">
                    By uploading content to ChatVia, you grant us a limited, non-exclusive license to process, store, and transmit your content solely for the purpose of providing our Service to you. This license terminates when you delete your content or account.
                </p>

                <h3 class="text-xl font-medium text-gray-800">5.3 Content Responsibilities</h3>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li>You are solely responsible for the content you upload</li>
                    <li>You must have the right to upload and process all content</li>
                    <li>You must comply with all applicable data protection laws</li>
                    <li>You must not upload content that infringes third-party rights</li>
                </ul>
            </section>

            <!-- Third-Party Services -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">6. Third-Party AI Providers</h2>
                <p class="text-gray-700 leading-relaxed">
                    ChatVia integrates with third-party AI providers to deliver our services. When you use our Service, your content may be processed by:
                </p>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li><strong>Cohere (embev-v4 and rerank):</strong> For document embedding and search re-ranking</li>
                    <li><strong>OpenAI (GPT-4.1):</strong> For document processing</li>
                </ul>

                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <h4 class="font-semibold text-amber-800 mb-2">Third-Party Terms:</h4>
                    <p class="text-amber-700 text-sm">
                        Your use of third-party AI services through ChatVia may be subject to additional terms and privacy policies from these providers. We recommend reviewing their terms and privacy policies.
                    </p>
                </div>
            </section>

            <!-- Intellectual Property -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">7. Intellectual Property</h2>

                <h3 class="text-xl font-medium text-gray-800">7.1 ChatVia Property</h3>
                <p class="text-gray-700 leading-relaxed">
                    The ChatVia platform, including its software, algorithms, design, and documentation, is owned by us and protected by intellectual property laws. You may not copy, modify, or distribute our platform without permission.
                </p>

                <h3 class="text-xl font-medium text-gray-800">7.2 Feedback and Suggestions</h3>
                <p class="text-gray-700 leading-relaxed">
                    If you provide feedback or suggestions about our Service, we may use that feedback to improve our platform without any obligation to compensate you.
                </p>
            </section>

            <!-- Privacy and Data Protection -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">8. Privacy and Data Protection</h2>
                <p class="text-gray-700 leading-relaxed">
                    Your privacy is important to us. Our collection and use of your information is governed by our Privacy Policy, which is incorporated into these Terms by reference. Key points include:
                </p>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li>Data is stored in EU</li>
                    <li>All data is encrypted in transit and at rest</li>
                    <li>You can delete your data at any time</li>
                    <li>EU residents have full GDPR rights regardless of chosen region</li>
                </ul>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-green-700 text-sm">
                        <strong>Privacy Policy:</strong> For complete details about our data practices, please review our
                        <a href="/privacy-policy" class="underline font-medium">Privacy Policy</a>.
                    </p>
                </div>
            </section>

            <!-- Service Availability -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">9. Service Availability and Modifications</h2>

                <h3 class="text-xl font-medium text-gray-800">9.1 Service Availability</h3>
                <p class="text-gray-700 leading-relaxed">
                    We strive to provide reliable service but cannot guarantee 100% uptime. Our Service may be temporarily unavailable due to maintenance, updates, or technical issues.
                </p>

                <h3 class="text-xl font-medium text-gray-800">9.2 Service Modifications</h3>
                <p class="text-gray-700 leading-relaxed">
                    We reserve the right to modify, update, or discontinue features of our Service at any time. We will provide reasonable notice of significant changes that may affect your use of the Service.
                </p>
            </section>

            <!-- Payment and Billing -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">10. Payment and Billing</h2>

                <h3 class="text-xl font-medium text-gray-800">10.1 Fees</h3>
                <p class="text-gray-700 leading-relaxed">
                    Use of ChatVia may be subject to fees as described in our pricing plans. All fees are due in advance and are non-refundable unless otherwise specified.
                </p>

                <h3 class="text-xl font-medium text-gray-800">10.2 Billing</h3>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li>Billing cycles begin on the date of your first payment</li>
                    <li>Automatic renewal applies unless you cancel your subscription</li>
                    <li>Price changes will be communicated with at least 30 days notice</li>
                    <li>Failed payments may result in service suspension</li>
                </ul>
            </section>

            <!-- Limitation of Liability -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">11. Disclaimers and Limitation of Liability</h2>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h3 class="text-lg font-medium text-gray-800 mb-3">11.1 Service Disclaimers</h3>
                    <p class="text-gray-700 text-sm mb-2">
                        OUR SERVICE IS PROVIDED "AS IS" WITHOUT WARRANTIES OF ANY KIND. WE DISCLAIM ALL WARRANTIES, EXPRESS OR IMPLIED, INCLUDING:
                    </p>
                    <ul class="list-disc list-inside text-gray-600 space-y-1 ml-4 text-sm">
                        <li>Accuracy or reliability of AI-generated responses</li>
                        <li>Uninterrupted or error-free service</li>
                        <li>Fitness for any particular purpose</li>
                        <li>Security against all possible threats</li>
                    </ul>
                </div>

                <h3 class="text-xl font-medium text-gray-800">11.2 AI Response Disclaimer</h3>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-yellow-800 text-sm">
                        <strong>Important:</strong> AI-generated responses are not guaranteed to be accurate, complete, or suitable for any specific purpose. Always verify important information and do not rely solely on AI responses for critical decisions, especially regarding medical, legal, or financial matters.
                    </p>
                </div>

                <h3 class="text-xl font-medium text-gray-800">11.3 Limitation of Liability</h3>
                <p class="text-gray-700 leading-relaxed">
                    TO THE MAXIMUM EXTENT PERMITTED BY LAW, OUR LIABILITY TO YOU IS LIMITED TO THE AMOUNT YOU PAID US IN THE 12 MONTHS PRECEDING THE CLAIM. WE SHALL NOT BE LIABLE FOR INDIRECT, INCIDENTAL, OR CONSEQUENTIAL DAMAGES.
                </p>
            </section>

            <!-- Termination -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">12. Termination</h2>

                <h3 class="text-xl font-medium text-gray-800">12.1 Termination by You</h3>
                <p class="text-gray-700 leading-relaxed">
                    You may terminate your account at any time through your account settings. Upon termination, you will lose access to your agents and data, which will be deleted according to our data retention policy.
                </p>

                <h3 class="text-xl font-medium text-gray-800">12.2 Termination by Us</h3>
                <p class="text-gray-700 leading-relaxed">
                    We may suspend or terminate your account if you violate these Terms, engage in prohibited activities, or if required by law. We will provide reasonable notice unless immediate termination is necessary.
                </p>

                <h3 class="text-xl font-medium text-gray-800">12.3 Effect of Termination</h3>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li>Your right to use the Service immediately ceases</li>
                    <li>Your data will be deleted immediately</li>
                    <li>Certain provisions of these Terms survive termination</li>
                    <li>No refunds for unused portions of paid services</li>
                </ul>
            </section>

            <!-- Governing Law -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">13. Governing Law and Disputes</h2>

                <h3 class="text-xl font-medium text-gray-800">13.1 Governing Law</h3>
                <p class="text-gray-700 leading-relaxed">
                    These Terms are governed by the laws of the jurisdiction where ChatVia is incorporated, without regard to conflict of law principles.
                </p>

                <h3 class="text-xl font-medium text-gray-800">13.2 Dispute Resolution</h3>
                <p class="text-gray-700 leading-relaxed">
                    We encourage resolving disputes through direct communication. If formal resolution is needed, disputes will be resolved through binding arbitration or in the courts of our jurisdiction.
                </p>
            </section>

            <!-- Contact and Changes -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">14. Changes and Contact Information</h2>

                <h3 class="text-xl font-medium text-gray-800">14.1 Changes to Terms</h3>
                <p class="text-gray-700 leading-relaxed">
                    We may update these Terms from time to time. Material changes will be communicated through email or prominent notice in our Service. Continued use constitutes acceptance of the updated Terms.
                </p>

                <h3 class="text-xl font-medium text-gray-800">14.2 Contact Information</h3>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h4 class="font-semibold text-blue-800 mb-3">Contact Us</h4>
                    <div class="space-y-2 text-blue-700">
                        <p><strong>General Support:</strong> <a href="mailto:info@chatvia.ai" class="underline">info@chatvia.ai</a></p>
                    </div>
                </div>
            </section>

            <!-- Miscellaneous -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">15. Miscellaneous</h2>

                <h3 class="text-xl font-medium text-gray-800">15.1 Entire Agreement</h3>
                <p class="text-gray-700 leading-relaxed">
                    These Terms, together with our Privacy Policy, constitute the entire agreement between you and ChatVia regarding your use of our Service.
                </p>

                <h3 class="text-xl font-medium text-gray-800">15.2 Severability</h3>
                <p class="text-gray-700 leading-relaxed">
                    If any provision of these Terms is found to be invalid or unenforceable, the remaining provisions will continue in full force and effect.
                </p>

                <h3 class="text-xl font-medium text-gray-800">15.3 Assignment</h3>
                <p class="text-gray-700 leading-relaxed">
                    You may not assign these Terms without our written consent. We may assign these Terms in connection with a merger, acquisition, or sale of assets.
                </p>
            </section>

            <!-- Footer -->
            <div class="border-t pt-6 text-center text-gray-500 text-sm">
                <p>These Terms of Service are effective as of 10 Aug 2025 and apply to all users of ChatVia.</p>
                <p class="mt-2">
                    By using ChatVia, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service.
                </p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
