<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - ChatVia</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                <a href="/" class="text-sm text-gray-600 hover:text-gray-900">← Back to Home</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-sm border p-8 space-y-8">
            <!-- Title -->
            <div class="text-center border-b pb-6">
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Privacy Policy</h1>
                <p class="text-gray-600">Last updated: {{ date('F j, Y') }}</p>
            </div>

            <!-- Introduction -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">1. Introduction</h2>
                <p class="text-gray-700 leading-relaxed">
                    Welcome to ChatVia ("we," "our," or "us"). This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our service.
                </p>
                <p class="text-gray-700 leading-relaxed">
                    ChatVia is designed to help you create AI agents by ingesting your documents and using advanced AI technologies to provide contextual responses. We are committed to protecting your privacy and ensuring transparency about our data practices.
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
                    When you create training-based agents, ChatVia processes your documents through our RAG (Retrieval-Augmented Generation) and GraphRAG systems. This involves:
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
                    <li>Improving our AI models and algorithms</li>
                    <li>Analyzing usage patterns to enhance user experience</li>
                    <li>Monitoring system performance and reliability</li>
                </ul>
            </section>

            <!-- Third-Party AI Providers -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">4. Third-Party AI Providers</h2>
                <p class="text-gray-700 leading-relaxed">
                    ChatVia integrates with various AI providers to deliver our services. Your data may be processed by:
                </p>

                <h3 class="text-xl font-medium text-gray-800">4.1 Data Processing Providers</h3>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li><strong>Cohere:</strong> For document embedding and processing (Privacy Policy: <a href="https://cohere.com/privacy" class="text-blue-600 hover:underline">https://cohere.com/privacy</a>)</li>
                    <li><strong>Mistral AI:</strong> For EU users requiring GDPR-compliant processing</li>
                    <li><strong>OpenAI:</strong> For US users accessing advanced AI capabilities</li>
                    <li><strong>Google AI:</strong> For US users requiring Google's AI services</li>
                </ul>

                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <h4 class="font-semibold text-amber-800 mb-2">Important Notice:</h4>
                    <p class="text-amber-700 text-sm">
                        When using AI models from third-party providers, your chat interactions and document content may be subject to their respective privacy policies and data processing practices. We recommend reviewing their privacy policies for complete transparency about how your data is handled.
                    </p>
                </div>

                <h3 class="text-xl font-medium text-gray-800">4.2 No Training on Your Data</h3>
                <p class="text-gray-700 leading-relaxed">
                    <strong>Important:</strong> We do not train AI models on your data. ChatVia uses RAG technology to find relevant information from your documents and send it to your chosen AI model for processing, but your data is not used to train or improve the underlying AI models.
                </p>
            </section>

            <!-- Data Storage and Security -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">5. Data Storage and Security</h2>

                <h3 class="text-xl font-medium text-gray-800">5.1 Storage Locations</h3>
                <p class="text-gray-700 leading-relaxed">
                    Based on your region selection during registration:
                </p>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li><strong>EU Users:</strong> Data stored and encrypted in EU data centers (Germany/Finland)</li>
                    <li><strong>US Users:</strong> Data stored and encrypted in US data centers</li>
                </ul>

                <h3 class="text-xl font-medium text-gray-800">5.2 Security Measures</h3>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li>End-to-end encryption for data in transit and at rest</li>
                    <li>Regular security audits and monitoring</li>
                    <li>Access controls and authentication protocols</li>
                    <li>Secure backup and disaster recovery procedures</li>
                </ul>
            </section>

            <!-- GDPR Rights -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">6. Your Rights Under GDPR</h2>
                <p class="text-gray-700 leading-relaxed">
                    If you are an EU resident, regardless of your chosen data processing region, you have the following rights under the General Data Protection Regulation (GDPR):
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
                    We retain your data only as long as necessary to provide our services and comply with legal obligations:
                </p>
                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                    <li><strong>Account Data:</strong> Retained while your account is active</li>
                    <li><strong>Chat History:</strong> Retained according to your settings</li>
                    <li><strong>Uploaded Documents:</strong> Retained until you delete them</li>
                    <li><strong>Usage Logs:</strong> Retained for up to 12 months for security and service improvement</li>
                </ul>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h4 class="font-semibold text-green-800 mb-2">Easy Data Deletion</h4>
                    <p class="text-green-700 text-sm">
                        You can easily delete your data at any time through your account settings. When you delete data, it is permanently removed from our systems within 30 days.
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
                    ChatVia is not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If we become aware that we have collected personal information from a child under 13, we will take steps to delete such information.
                </p>
            </section>

            <!-- Changes to Privacy Policy -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">10. Changes to This Privacy Policy</h2>
                <p class="text-gray-700 leading-relaxed">
                    We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last updated" date. We encourage you to review this Privacy Policy periodically for any changes.
                </p>
            </section>

            <!-- Contact Information -->
            <section class="space-y-4">
                <h2 class="text-2xl font-semibold text-gray-900">11. Contact Us</h2>
                <p class="text-gray-700 leading-relaxed">
                    If you have any questions about this Privacy Policy, want to exercise your rights, or need to contact our Data Protection Officer, please reach out to us:
                </p>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h4 class="font-semibold text-blue-800 mb-3">Privacy Contact Information</h4>
                    <div class="space-y-2 text-blue-700">
                        <p><strong>Email:</strong> <a href="mailto:privacy@chatvia.com" class="underline">privacy@chatvia.com</a></p>
                        <p><strong>Data Protection Officer:</strong> <a href="mailto:dpo@chatvia.com" class="underline">dpo@chatvia.com</a></p>
                        <p><strong>Response Time:</strong> We will respond to your privacy inquiries within 30 days</p>
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
                <p>This Privacy Policy is effective as of {{ date('F j, Y') }} and applies to all users of ChatVia.</p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
