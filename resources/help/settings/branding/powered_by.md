Controls the small "Powered by ChatVia.ai" line under hosted chats and chat widgets.

- **Show the credit**: When on, the line shows under every agent. You can turn it off only with a valid license key. Without one, the line shows even when the switch is off.
- **License key**: The key you get by email when you buy a license at chatvia.ai. A key costs $399, one time, and never expires.

A key works on one domain at a time. When you save a key, this install sends it and its domain (the domain in `APP_URL`, such as `chat.example.com`) to chatvia.ai to activate it. A key that is active on another domain is refused. Saving the same key again on the same domain is fine.

To move a key to another install, remove it here (it is then revoked at chatvia.ai), or revoke it on the **Manage license** page at chatvia.ai/license/manage. Once a day this install asks chatvia.ai whether its key is still active. If it is not, the key is removed and the credit shows again. If chatvia.ai can't be reached, nothing changes.
