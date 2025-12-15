<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - ChatVia | Data Protection & GDPR Compliance</title>

    @section('description',
        'ChatVia Privacy Policy - Learn how we protect your data, GDPR compliance, data processing
        with AI providers, and your privacy rights.')
    @section('og_title', 'Privacy Policy - ChatVia')
    @section('og_description',
        'Understand how ChatVia handles your data with complete transparency. GDPR compliant,
        secure storage, and full data control.')

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
                            <div class="text-2xl font-bold text-gray-900">ChatVia</div>
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
                        <h1 class="text-4xl font-bold text-gray-900 mb-2">Privacy Policy</h1>
                        <p class="text-gray-600">Last updated: 20 Dec 2025</p>
                    </div>

                    <!-- Introduction -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">1. Introduction</h2>
                        <p class="text-gray-700 leading-relaxed">
                            Welcome to ChatVia ("we," "our," or "us"). This Privacy Policy explains how we collect, use,
                            disclose, and safeguard your information when you use our service.
                        </p>
                        <p class="text-gray-700 leading-relaxed">
                            ChatVia is designed to help you create AI agents by ingesting your documents and using advanced
                            AI technologies to provide contextual responses. We are committed to protecting your privacy and
                            ensuring transparency about our data practices.
                        </p>
                    </section>

                    <!-- Information We Collect -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">2. Information We Collect</h2>

                        <h3 class="text-xl font-medium text-gray-800">2.1 Personal Information</h3>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li>Name and email address (for account creation)</li>
                            <li>Password (encrypted and stored securely)</li>
                            <li>Account settings and preferences</li>
                        </ul>

                        <h3 class="text-xl font-medium text-gray-800">2.2 Content and Documents</h3>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li>Documents you upload for agent training</li>
                            <li>Chat conversations with your agents</li>
                            <li>Agent configurations and settings</li>
                            <li>Metadata associated with your content</li>
                        </ul>

                        <h3 class="text-xl font-medium text-gray-800">2.3 Usage Information</h3>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li>Log data and usage patterns</li>
                            <li>Device information and IP addresses</li>
                            <li>Performance metrics and error reports</li>
                        </ul>
                    </section>

                    <!-- How We Use Your Information -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">3. How We Use Your Information</h2>

                        <h3 class="text-xl font-medium text-gray-800">3.1 RAG Processing</h3>
                        <p class="text-gray-700 leading-relaxed">
                            When you create training-based agents, ChatVia processes your documents through our RAG
                            (Retrieval-Augmented Generation). This involves:
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li>Document chunking and indexing</li>
                            <li>Vector embedding generation</li>
                            <li>Semantic search and retrieval</li>
                            <li>Context preparation for AI models</li>
                        </ul>

                        <h3 class="text-xl font-medium text-gray-800">3.2 Service Provision</h3>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li>Creating and managing your AI agents</li>
                            <li>Processing chat requests and generating responses</li>
                            <li>Maintaining account security and authentication</li>
                            <li>Providing customer support</li>
                        </ul>

                        <h3 class="text-xl font-medium text-gray-800">3.3 Improvement and Analytics</h3>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li>Improving our RAG system</li>
                            <li>Analyzing usage patterns to enhance user experience</li>
                            <li>Monitoring system performance and reliability</li>
                        </ul>
                    </section>

                    <!-- EU Infrastructure -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">4. EU-Based Infrastructure</h2>
                        <p class="text-gray-700 leading-relaxed">
                            <strong>Your data never leaves the European Union.</strong> ChatVia operates on 100% EU-based
                            infrastructure to ensure the highest level of data protection and GDPR compliance.
                        </p>

                        <h3 class="text-xl font-medium text-gray-800">4.1 Data Storage (Germany)</h3>
                        <p class="text-gray-700 leading-relaxed">
                            All your data, including documents, chat history, and account information, is stored exclusively
                            on <strong>Hetzner</strong> servers located in Germany. Hetzner is a German company operating
                            under strict EU data protection laws.
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li><strong>Database:</strong> All data securely stored in Germany</li>
                            <li><strong>Files & Documents:</strong> Uploaded files stored in Germany</li>
                            <li><strong>Location:</strong> 🇩🇪 Germany (EU)</li>
                            <li><strong>Privacy Policy:</strong> <a href="https://www.hetzner.com/legal/privacy-policy"
                                    class="text-blue-600 hover:underline" target="_blank" rel="noopener">Hetzner Privacy
                                    Policy</a></li>
                        </ul>

                        <h3 class="text-xl font-medium text-gray-800">4.2 Document Parsing (EU)</h3>
                        <p class="text-gray-700 leading-relaxed">
                            Document parsing and extraction is handled by <strong>LlamaCloud EU</strong>, provided by
                            LlamaIndex. This service processes your uploaded documents to extract text and structure.
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li><strong>Purpose:</strong> Document parsing, text extraction, and structure analysis</li>
                            <li><strong>Provider:</strong> LlamaIndex (LlamaCloud EU)</li>
                            <li><strong>Location:</strong> 🇪🇺 European Union</li>
                            <li><strong>Privacy:</strong> EU-based infrastructure with GDPR compliance</li>
                        </ul>

                        <h3 class="text-xl font-medium text-gray-800">4.3 AI Processing (France)</h3>
                        <p class="text-gray-700 leading-relaxed">
                            AI models and processing are provided by <strong>Scaleway</strong>, hosted exclusively in
                            France. We use state-of-the-art open-source AI models including Llama, DeepSeek, Qwen, Mistral,
                            and Gemma.
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li><strong>AI Models:</strong> Open-source models (Llama, DeepSeek, Qwen, Mistral, Gemma)</li>
                            <li><strong>Processing:</strong> All AI inference happens on Scaleway infrastructure</li>
                            <li><strong>Location:</strong> 🇫🇷 France (EU)</li>
                            <li><strong>Privacy Policy:</strong> <a href="https://www.scaleway.com/en/privacy-policy/"
                                    class="text-blue-600 hover:underline" target="_blank" rel="noopener">Scaleway Privacy
                                    Policy</a></li>
                        </ul>

                        <h3 class="text-xl font-medium text-gray-800">4.4 Payment Processing (Ireland)</h3>
                        <p class="text-gray-700 leading-relaxed">
                            Payment processing is handled by <strong>Stripe</strong>. For customers located outside the
                            Americas (including the EU, EEA, Switzerland, UK, Middle East, Africa, and Asia Pacific),
                            payments are processed by <strong>Stripe Technology Company, Limited (STC)</strong>, an Irish
                            company operating under Irish and EU law.
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li><strong>Entity:</strong> Stripe Technology Company, Limited (STC)</li>
                            <li><strong>Location:</strong> 🇮🇪 Ireland (EU)</li>
                            <li><strong>Role:</strong> Data controller for payment processing</li>
                            <li><strong>Compliance:</strong> GDPR compliant, authorized under Irish law</li>
                            <li><strong>Data Processed:</strong> Payment card details, billing information, transaction
                                history</li>
                            <li><strong>Privacy Policy:</strong> <a href="https://stripe.com/privacy"
                                    class="text-blue-600 hover:underline" target="_blank" rel="noopener">Stripe Privacy
                                    Policy</a></li>
                        </ul>
                        <p class="text-gray-700 leading-relaxed mt-3">
                            <strong>Important:</strong> We do not store your complete payment card details on our servers.
                            Payment information is securely processed and stored by Stripe in compliance with PCI-DSS
                            standards.
                        </p>

                        <h3 class="text-xl font-medium text-gray-800">4.5 Content Delivery Network (Slovenia)</h3>
                        <p class="text-gray-700 leading-relaxed">
                            We use <strong>Bunny CDN</strong>, headquartered in Slovenia, to deliver static content like
                            images and interface elements quickly to users worldwide. Bunny CDN is fully GDPR compliant.
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li><strong>Purpose:</strong> Fast delivery of static assets only</li>
                            <li><strong>Headquarters:</strong> 🇸🇮 Slovenia (EU)</li>
                            <li><strong>GDPR Compliance:</strong> <a href="https://bunny.net/gdpr/"
                                    class="text-blue-600 hover:underline" target="_blank" rel="noopener">Bunny CDN GDPR
                                    Information</a></li>
                        </ul>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="font-semibold text-blue-800 mb-2">✓ Zero US Data Processing</h4>
                            <p class="text-blue-700 text-sm">
                                Unlike many AI platforms, ChatVia does not process any data in the United States or share
                                data with US-based providers. All infrastructure, data storage, and AI processing remain
                                within the European Union, ensuring full compliance with EU data protection regulations.
                            </p>
                        </div>

                        <h3 class="text-xl font-medium text-gray-800">4.6 No Training on Your Data</h3>
                        <p class="text-gray-700 leading-relaxed">
                            <strong>Important:</strong> We do not train AI models on your data. ChatVia uses RAG
                            (Retrieval-Augmented Generation) technology to find relevant information from your documents and
                            send it to your chosen AI model for processing. Your data is never used to train or improve the
                            underlying AI models.
                        </p>
                    </section>

                    <!-- Data Storage and Security -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">5. Data Storage and Security</h2>

                        <h3 class="text-xl font-medium text-gray-800">5.1 Storage Locations</h3>
                        <p class="text-gray-700 leading-relaxed">
                            All your data is stored exclusively within the European Union:
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li><strong>Data Storage:</strong> Hetzner data centers in Germany 🇩🇪</li>
                            <li><strong>Uploaded Files:</strong> Securely stored in Germany 🇩🇪</li>
                            <li><strong>Document Parsing:</strong> LlamaCloud EU (European Union) 🇪🇺</li>
                            <li><strong>AI Processing:</strong> Scaleway infrastructure in France 🇫🇷</li>
                            <li><strong>Payment Processing:</strong> Stripe Technology Company, Limited (Ireland) 🇮🇪</li>
                            <li><strong>Content Delivery:</strong> Bunny CDN (headquartered in Slovenia 🇸🇮)</li>
                        </ul>

                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h4 class="font-semibold text-green-800 mb-2">🇪🇺 EU Data Sovereignty</h4>
                            <p class="text-green-700 text-sm">
                                Your data is protected by European data protection laws and never leaves EU borders. All our
                                infrastructure partners (Hetzner, Scaleway, Bunny CDN) are EU-based companies committed to
                                GDPR compliance.
                            </p>
                        </div>

                        <h3 class="text-xl font-medium text-gray-800">5.2 Security Measures</h3>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li>Data encryption at rest and in transit (TLS/HTTPS)</li>
                            <li>Regular security audits and monitoring</li>
                            <li>Access controls and multi-factor authentication</li>
                            <li>Secure backup and disaster recovery procedures</li>
                            <li>Network isolation and firewall protection</li>
                            <li>Compliance with ISO 27001 and SOC 2 standards</li>
                        </ul>
                    </section>

                    <!-- GDPR Rights -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">6. Your Rights Under GDPR</h2>
                        <p class="text-gray-700 leading-relaxed">
                            If you are an EU resident, you have the following rights under the General Data Protection
                            Regulation (GDPR):
                        </p>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="border rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 mb-2">Right to Access</h4>
                                <p class="text-gray-600 text-sm">Request a copy of your personal data we hold</p>
                            </div>
                            <div class="border rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 mb-2">Right to Rectification</h4>
                                <p class="text-gray-600 text-sm">Correct inaccurate or incomplete data</p>
                            </div>
                            <div class="border rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 mb-2">Right to Erasure</h4>
                                <p class="text-gray-600 text-sm">Request deletion of your personal data</p>
                            </div>
                            <div class="border rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 mb-2">Right to Portability</h4>
                                <p class="text-gray-600 text-sm">Transfer your data to another service</p>
                            </div>
                            <div class="border rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 mb-2">Right to Object</h4>
                                <p class="text-gray-600 text-sm">Object to processing of your personal data</p>
                            </div>
                            <div class="border rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 mb-2">Right to Restrict</h4>
                                <p class="text-gray-600 text-sm">Limit how we process your data</p>
                            </div>
                        </div>
                    </section>

                    <!-- Data Retention -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">7. Data Retention and Deletion</h2>
                        <p class="text-gray-700 leading-relaxed">
                            We retain your data only as long as necessary to provide our services and comply with legal
                            obligations:
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li><strong>Account Data:</strong> Retained while your account is active</li>
                            <li><strong>Chat History:</strong> Retained according to your settings</li>
                            <li><strong>Uploaded Documents:</strong> Retained until you delete them</li>
                            <li><strong>Usage Logs:</strong> Retained until you delete your account</li>
                        </ul>

                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h4 class="font-semibold text-green-800 mb-2">Easy Data Deletion</h4>
                            <p class="text-green-700 text-sm">
                                You can delete your data at any time through your account settings. When you choose to
                                delete data, it is permanently removed from our systems immediately, including all
                                documents, agents, and metadata (such as images) associated with your documents. This action
                                cannot be undone, and once deleted, we cannot recover your account or its contents.
                            </p>
                        </div>
                    </section>

                    <!-- Cookies and Tracking -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">8. Cookies and Tracking</h2>
                        <p class="text-gray-700 leading-relaxed">
                            We use essential cookies and similar technologies to:
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li>Maintain your login session</li>
                            <li>Remember your preferences</li>
                            <li>Ensure security and prevent fraud</li>
                            <li>Analyze usage patterns (with your consent)</li>
                        </ul>
                    </section>

                    <!-- Children's Privacy -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">9. Children's Privacy</h2>
                        <p class="text-gray-700 leading-relaxed">
                            ChatVia is not intended for children under 13 years of age. We do not knowingly collect personal
                            information from children under 13. If we become aware that we have collected personal
                            information from a child under 13, we will take steps to delete such information.
                        </p>
                    </section>

                    <!-- Changes to Privacy Policy -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">10. Changes to This Privacy Policy</h2>
                        <p class="text-gray-700 leading-relaxed">
                            We may update this Privacy Policy from time to time. We will notify you of any changes by
                            posting the new Privacy Policy on this page and updating the "Last updated" date. We encourage
                            you to review this Privacy Policy periodically for any changes.
                        </p>
                    </section>

                    <!-- Contact Information -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">11. Contact Us</h2>
                        <p class="text-gray-700 leading-relaxed">
                            If you have any questions about this Privacy Policy, want to exercise your rights, or need to
                            contact our Data Protection Officer, please reach out to us:
                        </p>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h4 class="font-semibold text-blue-800 mb-3">Privacy Contact Information</h4>
                            <div class="space-y-2 text-blue-700">
                                <p><strong>Email:</strong> <a href="mailto:info@chatvia.ai"
                                        class="underline">info@chatvia.ai</a></p>
                            </div>
                        </div>
                    </section>

                    <!-- Legal Basis -->
                    <section class="space-y-4">
                        <h2 class="text-2xl font-semibold text-gray-900">12. Legal Basis for Processing</h2>
                        <p class="text-gray-700 leading-relaxed">
                            Under GDPR, we process your personal data based on the following legal grounds:
                        </p>
                        <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                            <li><strong>Contract Performance:</strong> To provide ChatVia services you've requested</li>
                            <li><strong>Legitimate Interest:</strong> To improve our services and ensure security</li>
                            <li><strong>Consent:</strong> For optional features like analytics (where applicable)</li>
                            <li><strong>Legal Obligation:</strong> To comply with applicable laws and regulations</li>
                        </ul>
                    </section>

                    <!-- Footer -->
                    <div class="border-t pt-6 text-center text-gray-500 text-sm">
                        <p>This Privacy Policy is effective as of 10 Aug 2025 and applies to all users of ChatVia.</p>
                    </div>
                </div>
            </main>
        </div>
    </body>

    </html>
