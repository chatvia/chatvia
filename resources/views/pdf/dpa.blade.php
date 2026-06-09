<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Data Processing Agreement — Chatvia</title>
    <style>
        * { box-sizing: border-box; }

        @page { margin: 18mm 16mm; }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1f2937;
            font-size: 10.5pt;
            line-height: 1.5;
            margin: 0;
        }

        h1 { font-size: 19pt; margin: 0 0 2px; color: #0f172a; }
        h2 { font-size: 12.5pt; margin: 18px 0 6px; color: #0f172a; }
        h3 { font-size: 11pt; margin: 13px 0 4px; color: #0f172a; }
        h4 { font-size: 10.5pt; margin: 10px 0 4px; color: #0f172a; }
        p { margin: 6px 0; }
        ul, ol { margin: 6px 0 6px 18px; }
        li { margin: 3px 0; }
        strong { color: #0f172a; }
        hr { border: 0; border-top: 1px solid #e5e7eb; margin: 16px 0; }

        table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 9.5pt; }
        th, td { border: 1px solid #d1d5db; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 600; }

        blockquote {
            margin: 8px 0;
            padding: 6px 10px;
            border-left: 3px solid #93c5fd;
            background: #eff6ff;
        }

        .subtitle { color: #6b7280; font-size: 9.5pt; margin: 0 0 14px; }

        .parties { width: 100%; border-collapse: collapse; margin: 12px 0 4px; }
        .parties td { border: 1px solid #d1d5db; padding: 9px 11px; vertical-align: top; width: 50%; }
        .parties .role {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .note {
            margin: 14px 0;
            padding: 8px 12px;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 6px;
            font-size: 9.5pt;
            color: #3730a3;
        }

        .sig { width: 100%; margin-top: 26px; border-collapse: collapse; page-break-inside: avoid; }
        .sig td { width: 50%; padding: 0 18px 0 0; vertical-align: top; }
        .sig .line {
            border-top: 1px solid #374151;
            margin-top: 34px;
            padding-top: 4px;
            font-size: 9pt;
            color: #6b7280;
        }

        .meta { color: #9ca3af; font-size: 8.5pt; margin-top: 18px; }
    </style>
</head>

<body>
    <h1>Data Processing Agreement</h1>
    <p class="subtitle">Article 28 GDPR · Processor agreement for the Chatvia service</p>

    <p>This Data Processing Agreement (the <strong>&ldquo;DPA&rdquo;</strong>) forms part of, and is incorporated
        into, the agreement for the provision of the Chatvia service (the <strong>&ldquo;Principal
            Agreement&rdquo;</strong>) between the parties below. It gives effect to Article 28(3) of Regulation (EU)
        2016/679 (<strong>&ldquo;GDPR&rdquo;</strong>).</p>

    <table class="parties">
        <tr>
            <td>
                <div class="role">Processor</div>
                <strong>Akkadian</strong> (CVR/VAT 35709738)<br>
                Skovbrynet 50, 1 tv, Næstved, Denmark<br>
                operating the Chatvia service (<strong>&ldquo;Chatvia&rdquo;</strong>)
            </td>
            <td>
                <div class="role">Controller</div>
                <strong>{{ $companyName }}</strong>@if ($registrationNumber) (reg. no. {{ $registrationNumber }})@endif<br>
                @if ($registeredAddress){{ $registeredAddress }}<br>@endif
                @if ($country){{ $country }}<br>@endif
                @if ($contactEmail){{ $contactEmail }}<br>@endif
                (the <strong>&ldquo;Customer&rdquo;</strong>)
            </td>
        </tr>
    </table>

    <div class="note">
        This Data Processing Agreement becomes effective only when signed by both parties. To have Chatvia
        countersign, contact <strong>info@chatvia.ai</strong>.
    </div>

    {{-- Trusted, version-controlled DPA body (clauses 1–5 + Annexes 1–3). --}}
    {!! $bodyHtml !!}

    <h2>Signatures</h2>
    <table class="sig">
        <tr>
            <td>
                <strong>For the Processor — Akkadian (Chatvia)</strong>
                <div class="line">Name &amp; title</div>
                <div class="line">Signature</div>
                <div class="line">Date</div>
            </td>
            <td>
                <strong>For the Controller — {{ $companyName }}</strong>
                <div class="line">@if ($signatoryName){{ $signatoryName }}@if ($signatoryTitle), {{ $signatoryTitle }}@endif @else Name &amp; title @endif</div>
                <div class="line">Signature</div>
                <div class="line">Date</div>
            </td>
        </tr>
    </table>

    <p class="meta">Generated {{ $generatedAt }} from the Chatvia DPA template (version 1.0). The standard terms are
        offered on a non-negotiable basis; the parties&rsquo; identities and signatures complete the agreement.</p>
</body>

</html>
