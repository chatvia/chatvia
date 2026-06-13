@extends('landing.layouts.app')

@section('title', 'Chat with PDF - Free AI Tool | Ask Questions About Your Documents')

@section('description', 'Free AI-powered PDF chat tool. Upload any PDF document and ask questions instantly. Get intelligent answers, summaries, and insights from your PDFs using advanced AI.')

@section('og_title', 'Chat with PDF - Free AI Tool | ChatVia')
@section('og_description', 'Transform how you read PDFs. Upload documents, ask questions, and get instant AI-powered answers. Free, secure, and easy to use.')

@section('keywords', 'PDF chat, AI PDF reader, PDF analysis tool, chat with documents, PDF question answering, free PDF tool, AI document analysis')

@section('content')
<div class="flex flex-col min-h-screen overflow-hidden">

    @include('landing.partials.header')

    <!-- Page content -->
    <main class="grow">

        <!-- Hero -->
        <section class="relative">

            <!-- Dark background -->
            <div
                class="absolute inset-0 bg-slate-900 pointer-events-none -z-10 [clip-path:polygon(0_0,_5760px_0,_5760px_calc(100%_-_352px),_0_100%)]"
                aria-hidden="true"></div>

            <div class="flex justify-center max-w-6xl mx-auto px-4 sm:px-6">
                <div class="pt-32 pb-20 md:pt-40 md:pb-44">

                    <!-- Hero content -->
                    <div
                        class="max-w-xl mx-auto md:max-w-none flex flex-col items-center justify-center text-center md:space-y-16 space-y-16">

                        <div class="text-center">
                            <p class="text-xl text-slate-400">
                                Free AI-Powered Tool
                            </p>
                            <h1 class="mx-auto md:max-w-3xl text-5xl sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl font-extrabold font-display text-slate-100 mb-4 leading-tight text-center">
                                Chat with Your PDF Documents
                            </h1>

                            <p class="text-xl text-slate-400 mb-6">
                                Upload any PDF and start asking questions instantly. Get intelligent answers powered by advanced AI.
                            </p>

                            <p class="text-xl text-slate-400 mb-8">
                                Extract insights, summarize content, and find information faster than ever before.
                            </p>

                            <!-- Upload Section -->
                            <div class="max-w-md mx-auto">
                                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20">
                                    <!-- File Upload Area -->
                                    <div 
                                        class="border-2 border-dashed border-white/30 rounded-xl p-8 text-center hover:border-blue-400 transition-colors duration-300 cursor-pointer"
                                        id="upload-area"
                                        onclick="document.getElementById('pdf-upload').click()"
                                    >
                                        <div class="flex flex-col items-center space-y-4">
                                            <!-- Upload Icon -->
                                            <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center">
                                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                </svg>
                                            </div>
                                            
                                            <div>
                                                <p class="text-white font-medium mb-2">
                                                    Drop your PDF here or click to browse
                                                </p>
                                                <p class="text-slate-300 text-sm">
                                                    Maximum file size: 10MB
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <!-- Hidden file input -->
                                        <input 
                                            type="file" 
                                            id="pdf-upload" 
                                            accept=".pdf" 
                                            class="hidden"
                                        >
                                    </div>

                                    <!-- Upload Button -->
                                    <div class="mt-6">
                                        <button 
                                            id="upload-btn"
                                            class="btn text-white bg-blue-600 hover:bg-blue-700 w-full group disabled:opacity-50 disabled:cursor-not-allowed"
                                            disabled
                                        >
                                            <span id="btn-text">Select a PDF to get started</span>
                                            <span class="tracking-normal text-blue-300 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1">→</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <p class="text-sm text-slate-500 mt-6">
                                Your documents are processed securely and never stored permanently.
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="relative py-12 md:py-20">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                
                <!-- Section header -->
                <div class="max-w-3xl mx-auto text-center mb-12 md:mb-16">
                    <h2 class="h2 font-playfair-display text-slate-800 mb-4">
                        Why Choose Our PDF Chat Tool?
                    </h2>
                    <p class="text-xl text-slate-600">
                        Experience the power of AI-driven document analysis with these key features.
                    </p>
                </div>

                <!-- Features Grid -->
                <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3 items-start">
                    
                    <!-- Feature 1 -->
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800 mb-2">
                            Instant Analysis
                        </h3>
                        <p class="text-slate-600">
                            Get immediate insights from your PDF documents with lightning-fast AI processing.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800 mb-2">
                            Accurate Answers
                        </h3>
                        <p class="text-slate-600">
                            Receive precise, contextual answers based on the actual content of your documents.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-purple-100 rounded-full mb-4">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800 mb-2">
                            Secure & Private
                        </h3>
                        <p class="text-slate-600">
                            Your documents are processed securely and automatically deleted after your session.
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-orange-100 rounded-full mb-4">
                            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800 mb-2">
                            Natural Conversation
                        </h3>
                        <p class="text-slate-600">
                            Ask questions in plain English and get conversational responses that make sense.
                        </p>
                    </div>

                    <!-- Feature 5 -->
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800 mb-2">
                            Smart Summaries
                        </h3>
                        <p class="text-slate-600">
                            Generate concise summaries of lengthy documents to save time and improve comprehension.
                        </p>
                    </div>

                    <!-- Feature 6 -->
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-full mb-4">
                            <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800 mb-2">
                            Multiple Formats
                        </h3>
                        <p class="text-slate-600">
                            Support for various PDF types including text-based documents, forms, and research papers.
                        </p>
                    </div>

                </div>
            </div>
        </section>

        <!-- How it Works Section -->
        <section class="relative bg-slate-50 py-12 md:py-20">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                
                <!-- Section header -->
                <div class="max-w-3xl mx-auto text-center mb-12 md:mb-16">
                    <h2 class="h2 font-playfair-display text-slate-800 mb-4">
                        How It Works
                    </h2>
                    <p class="text-xl text-slate-600">
                        Get started with your PDF analysis in just three simple steps.
                    </p>
                </div>

                <!-- Steps -->
                <div class="grid gap-8 md:grid-cols-3 items-start">
                    
                    <!-- Step 1 -->
                    <div class="text-center">
                        <div class="relative mb-6">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 text-white rounded-full text-2xl font-bold">
                                1
                            </div>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800 mb-3">
                            Upload Your PDF
                        </h3>
                        <p class="text-slate-600">
                            Simply drag and drop your PDF file or click to browse and select it from your device.
                        </p>
                    </div>

                    <!-- Step 2 -->
                    <div class="text-center">
                        <div class="relative mb-6">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 text-white rounded-full text-2xl font-bold">
                                2
                            </div>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800 mb-3">
                            AI Processing
                        </h3>
                        <p class="text-slate-600">
                            Our advanced AI analyzes your document content and prepares it for intelligent questioning.
                        </p>
                    </div>

                    <!-- Step 3 -->
                    <div class="text-center">
                        <div class="relative mb-6">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 text-white rounded-full text-2xl font-bold">
                                3
                            </div>
                        </div>
                        <h3 class="text-xl font-semibold text-slate-800 mb-3">
                            Start Chatting
                        </h3>
                        <p class="text-slate-600">
                            Ask any questions about your document and receive instant, accurate answers based on the content.
                        </p>
                    </div>

                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="bg-slate-100">
            <div class="relative max-w-6xl mx-auto px-4 sm:px-6">
                <div class="py-12 md:py-20">

                    <div class="relative max-w-3xl mx-auto text-center">
                        <div class="relative">
                            <h2 class="h2 font-playfair-display text-slate-800 mb-4">
                                Ready to Transform How You Read PDFs?
                            </h2>
                            <p class="text-xl text-slate-500 mb-8">
                                Join thousands of users who are already saving time and gaining insights with our AI-powered PDF chat tool.
                            </p>
                            <div>
                                <button 
                                    onclick="document.getElementById('pdf-upload').click()"
                                    class="btn text-white bg-blue-600 hover:bg-blue-700 group"
                                >
                                    Get Started Now - It's Free
                                    <span class="tracking-normal text-blue-300 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1">→</span>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

    </main>

    @include('landing.partials.footer')

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('pdf-upload');
    const uploadBtn = document.getElementById('upload-btn');
    const btnText = document.getElementById('btn-text');

    // Handle file selection
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.type === 'application/pdf') {
                btnText.textContent = `Upload "${file.name}"`;
                uploadBtn.disabled = false;
                uploadBtn.classList.remove('disabled:opacity-50', 'disabled:cursor-not-allowed');
            } else {
                alert('Please select a PDF file.');
                fileInput.value = '';
            }
        }
    });

    // Handle drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('border-blue-400');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('border-blue-400');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('border-blue-400');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            if (file.type === 'application/pdf') {
                fileInput.files = files;
                btnText.textContent = `Upload "${file.name}"`;
                uploadBtn.disabled = false;
                uploadBtn.classList.remove('disabled:opacity-50', 'disabled:cursor-not-allowed');
            } else {
                alert('Please select a PDF file.');
            }
        }
    });

    // Handle upload button click
    uploadBtn.addEventListener('click', function() {
        if (fileInput.files.length > 0) {
            // Here you would typically handle the file upload
            // For now, we'll just show an alert
            alert('File upload functionality would be implemented here!');
        }
    });
});
</script>
@endsection
