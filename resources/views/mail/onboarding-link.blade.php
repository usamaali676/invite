<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#f1f5f5;font-family:Helvetica,Arial,sans-serif;color:#0e2233;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:540px;background:#ffffff;border:1px solid #dce4e5;border-radius:16px;padding:32px;">
                <tr><td>
                    <p style="margin:0 0 20px;font-size:15px;font-weight:bold;color:#0e2233;">{{ config('gbp.agency_name') }}</p>
                    <p style="margin:0 0 12px;font-size:16px;">Hello{{ $client->client_name ? ' '.\Illuminate\Support\Str::of($client->client_name)->trim()->before(' ') : '' }},</p>
                    @if ($manual)
                        <p style="margin:0 0 16px;font-size:16px;line-height:1.55;">We have sent a request to manage <strong>{{ $client->business_name }}</strong> on Google. Google needs you, as the owner, to approve it. The page below shows the steps and takes about two minutes.</p>
                    @else
                        <p style="margin:0 0 16px;font-size:16px;line-height:1.55;">We are ready to start managing the Google Business Profile for <strong>{{ $client->business_name }}</strong>. Please connect your Google account so we can request Manager access. It takes about two minutes.</p>
                    @endif
                    <p style="margin:24px 0;">
                        <a href="{{ $url }}" style="display:inline-block;background:#0f5c52;color:#ffffff;text-decoration:none;font-weight:bold;font-size:16px;padding:14px 24px;border-radius:12px;">{{ $manual ? 'See how to approve' : 'Connect my Google account' }}</a>
                    </p>
                    <p style="margin:0 0 6px;font-size:14px;font-weight:bold;">Good to know</p>
                    <ul style="margin:0 0 20px;padding-left:20px;font-size:14px;line-height:1.6;color:#46596a;">
                        <li>You sign in on Google's own page. We never see your password.</li>
                        <li>You stay the owner. We ask for Manager access only.</li>
                        <li>You can remove our access at any time in your Business Profile.</li>
                    </ul>
                    <p style="margin:0 0 6px;font-size:13px;color:#46596a;">Button not working? Paste this link into your browser:</p>
                    <p style="margin:0 0 20px;font-size:13px;word-break:break-all;color:#46596a;">{{ $url }}</p>
                    <p style="margin:0;font-size:13px;line-height:1.6;color:#46596a;border-top:1px solid #dce4e5;padding-top:16px;">
                        Not sure this is for you? Reply to this email or write to {{ config('gbp.support_email') }}@if (config('gbp.support_phone')), or call {{ config('gbp.support_phone') }}@endif.
                        <br>{{ config('gbp.agency_name') }}@if (config('gbp.company_address')), {{ config('gbp.company_address') }}@endif
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
