# PunktePass - Claude Code Notes

## Deploy Method

```bash
git fetch origin [BRANCH] && git checkout FETCH_HEAD -- [FILES]
```

### Example:
```bash
git fetch origin claude/review-code-promotion-01LWAg2uwMeFjk2rVRzXUTtk && git checkout FETCH_HEAD -- assets/css/ppv-theme-light.css assets/js/pp-profile-lite.js
```

## ⚠️ FONTOS: Deploy parancs megadása

**Minden fájlmódosítás után KÖTELEZŐ megadni az SSH deploy parancsot!**

Amikor fájlokat módosítasz és pusholsz, MINDIG írd ki a deploy parancsot a módosított fájlokkal:

```bash
git fetch origin [AKTUÁLIS_BRANCH] && git checkout FETCH_HEAD -- [MÓDOSÍTOTT_FÁJLOK]
```
