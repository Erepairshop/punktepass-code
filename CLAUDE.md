# PunktePass - Claude Code Notes

## SSH Deploy Method

```bash
ssh root@81.169.151.120 "cd /var/www/clients/client1/web1/web/wp-content/plugins/punktepass && git fetch origin [BRANCH] && git checkout FETCH_HEAD -- [FILES]"
```

### Example:
```bash
ssh root@81.169.151.120 "cd /var/www/clients/client1/web1/web/wp-content/plugins/punktepass && git fetch origin claude/review-code-promotion-01LWAg2uwMeFjk2rVRzXUTtk && git checkout FETCH_HEAD -- assets/css/ppv-theme-light.css assets/js/pp-profile-lite.js"
```
