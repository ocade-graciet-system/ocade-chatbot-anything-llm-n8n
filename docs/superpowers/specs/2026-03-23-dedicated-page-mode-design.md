# Design : Mode page dediee (#6)

## Contexte

Le chatbot s'affiche actuellement via une pastille flottante sur toutes les pages du site. L'issue #6 demande un mode alternatif ou le chatbot est accessible uniquement sur une page dediee.

## Decision

Approche retenue : page WordPress auto-creee + shortcode existant enrichi. Le plugin cree automatiquement une page WP standard contenant le shortcode `[ofac_chatbot fullscreen="true"]`. Pas de template custom ni de rewrite rule.

## Reglages

### Nouveau reglage : `ofac_display_mode`

- `floating` (defaut) : pastille flottante sur toutes les pages (comportement actuel)
- `dedicated` : page dediee uniquement, pastille desactivee partout

### Stockage page : `ofac_dedicated_page_id`

ID de la page WordPress creee automatiquement.

## Logique d'affichage

### Mode `floating`

Aucun changement. Comportement actuel.

### Mode `dedicated`

- `should_display()` retourne `false` systematiquement (pastille desactivee sur tout le site)
- Le shortcode `[ofac_chatbot]` continue de fonctionner independamment (il ne passe pas par `should_display()`)
- La page dediee affiche le chatbot en plein ecran via l'attribut `fullscreen="true"`

## Creation automatique de la page

### Declencheur

A la sauvegarde des reglages quand `display_mode` passe a `dedicated`, si `ofac_dedicated_page_id` n'existe pas ou si la page correspondante n'existe plus.

### Proprietes de la page creee

- **Titre** : `{ofac_bot_name} - Chatbot` (ex: "Assistant Ocade - Chatbot")
- **Contenu** : `[ofac_chatbot fullscreen="true"]`
- **Statut** : `publish`
- **Slug** : `chatbot`
- **Noindex** :
  - Si Yoast actif : meta `_yoast_wpseo_meta-robots-noindex` = `1`
  - Sinon : balise `<meta name="robots" content="noindex, nofollow">` via hook `wp_head` quand on est sur cette page

### Retour en mode `floating`

La page n'est pas supprimee. L'admin peut la supprimer manuellement ou la reutiliser plus tard.

### Page supprimee

Si la page referencee par `ofac_dedicated_page_id` n'existe plus et que le mode est `dedicated`, elle est recreee automatiquement au prochain chargement admin.

## Shortcode : attribut `fullscreen`

Le shortcode `[ofac_chatbot]` accepte un nouvel attribut `fullscreen` :

```
[ofac_chatbot fullscreen="true"]
```

Comportement :
- Le wrapper shortcode recoit la classe CSS `ofac-shortcode--fullscreen`
- Le JS detecte cette classe et active le fullscreen automatiquement au chargement
- Le bouton fullscreen existant (compress) permet de reduire en taille inline
- Header/footer du theme restent toujours visibles

## Controle d'acces

La page dediee reutilise les reglages existants :
- `ofac_require_login` : si active et utilisateur non connecte, le shortcode affiche un message "Connectez-vous pour acceder au chatbot"
- `ofac_allowed_roles` : si l'utilisateur n'a pas le role requis, meme message

Aucun nouveau reglage d'acces a creer.

## Affichage dans l'admin

Quand le mode `dedicated` est actif, afficher dans la section reglages :
- Un lien cliquable vers la page dediee
- Le slug de la page

## Fichiers impactes

| Fichier | Modification |
|---------|-------------|
| `includes/class-ofac-settings.php` | Ajout reglage `ofac_display_mode` |
| `includes/class-ofac-chatbot.php` | Modifier `should_display()` pour mode dedicated |
| `public/class-ofac-shortcode.php` | Support attribut `fullscreen="true"` |
| `public/class-ofac-public.php` | Logique creation page + noindex fallback |
| `assets/js/chatbot.js` | Auto-fullscreen au chargement si classe presente |
| `assets/css/chatbot.css` | Style `ofac-shortcode--fullscreen` |
