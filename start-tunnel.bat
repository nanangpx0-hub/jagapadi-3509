@echo off
title Cloudflare Tunnel - JAGAPADI (jagapadi.my.id)
echo Starting Cloudflare Tunnel for jagapadi.my.id...
"C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --config "C:\Users\IPDS\.cloudflared\config.yml" run jagapadi-server
pause
