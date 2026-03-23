# Design : Page access-denied chatbot

## Contexte

Quand un utilisateur non connecte arrive sur la page dediee du chatbot (mode `dedicated` avec `ofac_require_login` actif), il voit un simple paragraphe texte. Cette page doit etre attractive, presenter le chatbot, permettre de se connecter via wp-login, et offrir un lien retour vers l'accueil du site.

## Decision

Approche retenue : carte centree sur fond sombre. Reutilise les reglages existants (avatar, nom, couleur) + un nouveau reglage pour le message descriptif. Le bouton "Se connecter" redirige vers `wp_login_url()` avec retour automatique sur la page chatbot apres login.

## Layout

Carte centree verticalement et horizontalement contenant :

1. **Avatar du bot** — reutilise `ofac_bot_avatar`. Fallback : icone SVG generique (bulle de chat)
2. **Nom du bot** — reutilise `ofac_bot_name` (fallback : "Service Client" via le default du schema)
3. **Message descriptif** — nouveau reglage `ofac_login_page_message`, echappe via `esc_html()` (texte brut, pas de HTML)
4. **Bouton "Se connecter"** — lien `<a>` vers `wp_login_url( get_permalink() )` style bouton
5. **Lien "Retour a l'accueil"** — lien vers `home_url()`, sous le bouton

## Nouveau reglage

### `ofac_login_page_message`

- **Type** : textarea
- **Section** : Messages (apres `ofac_welcome_message`)
- **Label** : "Message de la page de connexion"
- **Description** : "Texte affiche aux visiteurs non connectes sur la page dediee du chatbot."
- **Default** : "Connectez-vous pour acceder a votre assistant."

### Valeur par defaut dans `load_settings()`

Ajouter dans le tableau `$setting_keys` de la methode `load_settings()` :

```php
'ofac_login_page_message' => 'Connectez-vous pour accéder à votre assistant.',
```

### Ajout dans `get_public_settings()`

Ne PAS ajouter `ofac_login_page_message` dans le tableau `$public_keys` de `get_public_settings()` (pas besoin cote frontend JS).

## Chargement des assets

Le code actuel retourne le HTML access-denied AVANT l'appel a `enqueue_assets()`. Il faut charger le CSS (`wp_enqueue_style('ofac-chatbot')`) avant de retourner le HTML access-denied, sinon la carte sera non stylee. Seul le CSS est necessaire, pas le JS.

## Cas d'affichage

### Plugin desactive ou API non configuree

Retourne une chaine vide (comportement actuel inchange). La page access-denied ne s'affiche que si le plugin est actif et l'API configuree.

### Utilisateur non connecte (`require_login` actif ou `allowed_roles` non vide)

Carte complete avec :
- Avatar + nom + message descriptif
- Bouton "Se connecter" → `wp_login_url( get_permalink() )`
- Lien "Retour a l'accueil" → `home_url()`

### Utilisateur connecte mais role non autorise

Meme carte mais :
- Avatar + nom
- Message : "Vous n'avez pas les droits pour acceder au chatbot." (hardcode, pas configurable)
- Pas de bouton login
- Lien "Retour a l'accueil" → `home_url()`

## Structure HTML

```html
<div class="ofac-access-denied">
  <div class="ofac-access-denied__card">
    <div class="ofac-access-denied__avatar">
      <!-- img si bot_avatar existe, sinon SVG fallback -->
    </div>
    <h2 class="ofac-access-denied__title">{bot_name}</h2>
    <p class="ofac-access-denied__message">{message}</p>
    <!-- Si non connecte -->
    <a href="{login_url}" class="ofac-access-denied__login-btn">Se connecter</a>
    <a href="{home_url}" class="ofac-access-denied__home-link">← Retour a l'accueil</a>
  </div>
</div>
```

## Style CSS

La classe `.ofac-access-denied` sert de conteneur plein ecran centre. La carte utilise les CSS custom properties du plugin pour le theming.

Toutes les couleurs utilisent les CSS custom properties du plugin (`--ofac-bg-secondary`, `--ofac-text`, `--ofac-text-secondary`, etc.) pour supporter light et dark theme automatiquement.

```css
.ofac-access-denied {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 80vh;
  background: var(--ofac-bg-secondary);
  font-family: var(--ofac-font-family);
}

.ofac-access-denied__card {
  background: var(--ofac-bg);
  border: 1px solid var(--ofac-border);
  border-radius: var(--ofac-radius-xl);
  padding: var(--ofac-spacing-xl) var(--ofac-spacing-xl);
  text-align: center;
  max-width: 400px;
  width: 100%;
  box-shadow: var(--ofac-shadow-lg);
}

.ofac-access-denied__avatar {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  margin: 0 auto var(--ofac-spacing-md);
  background: linear-gradient(135deg, var(--ofac-primary), var(--ofac-primary-hover));
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.ofac-access-denied__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ofac-access-denied__title {
  color: var(--ofac-text);
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 var(--ofac-spacing-sm);
}

.ofac-access-denied__message {
  color: var(--ofac-text-secondary);
  font-size: 0.95rem;
  line-height: 1.5;
  margin: 0 0 var(--ofac-spacing-lg);
}

.ofac-access-denied__login-btn {
  display: block;
  background: linear-gradient(135deg, var(--ofac-primary), var(--ofac-primary-hover));
  color: var(--ofac-text-inverse);
  text-decoration: none;
  padding: var(--ofac-spacing-sm) var(--ofac-spacing-lg);
  border-radius: var(--ofac-radius-lg);
  font-weight: 600;
  font-size: 0.95rem;
  margin-bottom: var(--ofac-spacing-md);
  transition: opacity 0.2s;
}

.ofac-access-denied__login-btn:hover {
  opacity: 0.9;
}

.ofac-access-denied__home-link {
  color: var(--ofac-text-secondary);
  font-size: 0.85rem;
  text-decoration: none;
  transition: color 0.2s;
}

.ofac-access-denied__home-link:hover {
  color: var(--ofac-primary);
}

@media (max-width: 480px) {
  .ofac-access-denied__card {
    padding: var(--ofac-spacing-lg) var(--ofac-spacing-md);
    margin: 0 var(--ofac-spacing-md);
  }
}
```

Le client peut overrider le fond (`.ofac-access-denied`), les couleurs de la carte, ou tout element via CSS custom dans son theme.

## Template dedicated-page.php

Le template `dedicated-page.php` a `overflow: hidden` sur le body. Pour le cas access-denied, il faut autoriser le scroll sur petits ecrans :

```css
.ofac-dedicated-body:has(.ofac-access-denied) {
  overflow: auto;
}
```

Ce style est ajoute dans le bloc `<style>` existant du template.

## Fichiers impactes

| Fichier | Modification |
|---------|-------------|
| `public/class-ofac-shortcode.php` | Ajouter `render_access_denied( bool $show_login = true ): string` — genere la carte complete. Charger `wp_enqueue_style('ofac-chatbot')` avant de retourner |
| `includes/class-ofac-settings.php` | Ajouter `ofac_login_page_message` dans schema (section Messages, entre `ofac_welcome_message` et `ofac_fallback_message`) + `load_settings()` defaults |
| `assets/css/chatbot.css` | Remplacer le style `.ofac-access-denied` existant par les styles BEM complets |
| `templates/dedicated-page.php` | Ajouter regle CSS `:has(.ofac-access-denied)` pour overflow auto |

## Hors scope

- Formulaire de login integre (on redirige vers wp-login)
- Personnalisation du fond via reglage (le client override en CSS)
- Lien "Mot de passe oublie" (gere par wp-login nativement)
