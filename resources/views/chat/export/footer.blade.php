<!-- Footer Section -->
<div class="footer">
    <div class="logo">
        <a href="https://chatvia.ai">ChatVia.AI</a>
    </div>
    <div class="center-info">
        Agent: {{ $agent }} • Generated on {{ now()->format('M j, Y g:i A T') }}
    </div>
    <div class="page-info">
        Page @pageNumber of @totalPages
    </div>
</div>

<style>
/* Footer Styles */
.footer {
    margin-top: 40px;
    padding: 20px 25px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: #64748b;
    page-break-inside: avoid;
}

.footer .logo {
    font-weight: 600;
    color: #374151;
    font-size: 12px;
}

.footer .logo a {
    color: #374151;
    text-decoration: none;
}

.footer .center-info {
    flex: 1;
    text-align: center;
    font-size: 11px;
}

.footer .page-info {
    font-size: 11px;
    color: #64748b;
    font-weight: 500;
}

/* Print Optimizations for Footer */
@media print {
    .footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        margin-top: 0;
        padding: 15px 25px;
        background: white;
        border-top: 1px solid #e2e8f0;
        font-size: 10px;
    }

    .footer .center-info {
        font-size: 10px;
    }

    .footer .page-info {
        font-size: 10px;
    }

    /* Page counter support with page numbers */
    @page {
        margin: 12mm 12mm 25mm 12mm;
    }
}
</style> 