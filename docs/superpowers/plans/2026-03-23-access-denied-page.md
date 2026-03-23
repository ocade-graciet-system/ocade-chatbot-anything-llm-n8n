# Access-Denied Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the bare access-denied text with a branded card (avatar, bot name, description, login button, home link) for non-logged-in users on the chatbot dedicated page.

**Architecture:** Add a `render_access_denied()` method to the shortcode class that builds the card HTML using existing plugin settings + a new `ofac_login_page_message` setting. CSS uses existing design tokens for theme compatibility. The dedicated-page template gets a minor overflow fix.

**Tech Stack:** PHP (WordPress plugin), CSS

**Spec:** `docs/superpowers/specs/2026-03-23-access-denied-page-design.md`

---

### Task 1: Add `ofac_login_page_message` setting

**Files:**
- Modify: `includes/class-ofac-settings.php:247-252` (schema, section Messages)
- Modify: `includes/class-ofac-settings.php:543` (load_settings defaults)

- [ ] **Step 1: Add field to schema**

In `get_schema()`, section Messages (`ofac_messages`), insert between `ofac_welcome_message` and `ofac_fallback_message`:

```php
                    'ofac_login_page_message' => array(
                        'type'        => 'textarea',
                        'label'       => __( 'Message de la page de connexion', 'anythingllm-chatbot' ),
                        'description' => __( 'Texte affiché aux visiteurs non connectés sur la page dédiée du chatbot.', 'anythingllm-chatbot' ),
                        'default'     => __( 'Connectez-vous pour accéder à votre assistant.', 'anythingllm-chatbot' ),
                    ),
```

- [ ] **Step 2: Add default in `load_settings()`**

In the `$setting_keys` array, insert after `'ofac_welcome_message'`:

```php
            'ofac_login_page_message'   => 'Connectez-vous pour accéder à votre assistant.',
```

- [ ] **Step 3: Commit**

```bash
git add includes/class-ofac-settings.php
git commit -m "feat: add ofac_login_page_message setting"
```

---

### Task 2: Replace access-denied CSS

**Files:**
- Modify: `assets/css/chatbot.css:2581-2586` (replace `.ofac-access-denied` block)

- [ ] **Step 1: Replace the existing `.ofac-access-denied` styles**

Remove the existing block at lines 2581-2586 and replace with the full BEM styles:

```css
/* ==========================================================================
   Access Denied Card
   ========================================================================== */

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
    padding: var(--ofac-spacing-xl);
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

.ofac-access-denied__avatar svg {
    width: 36px;
    height: 36px;
    fill: var(--ofac-text-inverse);
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

- [ ] **Step 2: Commit**

```bash
git add assets/css/chatbot.css
git commit -m "feat: access-denied card BEM styles with design tokens"
```

---

### Task 3: Add `render_access_denied()` method and wire it up

**Files:**
- Modify: `public/class-ofac-shortcode.php:31-57` (replace bare HTML returns + add method)

- [ ] **Step 1: Add the `render_access_denied` method**

Add this private method at the end of the `OFAC_Shortcode` class (before the closing `}`):

```php
    /**
     * Render access denied card
     *
     * @param bool $show_login Whether to show the login button.
     * @return string
     */
    private function render_access_denied( $show_login = true ) {
        wp_enqueue_style( 'ofac-chatbot' );

        $settings  = OFAC_Settings::get_instance();
        $bot_name  = esc_html( $settings->get( 'ofac_bot_name', 'Service Client' ) );
        $bot_avatar_id = $settings->get( 'ofac_bot_avatar', '' );

        // Avatar: image or SVG fallback
        $avatar_url = $bot_avatar_id ? wp_get_attachment_url( $bot_avatar_id ) : false;
        if ( $avatar_url ) {
            $avatar_html = '<img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $settings->get( 'ofac_bot_name', 'Service Client' ) ) . '">';
        } else {
            $avatar_html = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>';
        }

        // Message
        if ( $show_login ) {
            $message = esc_html( $settings->get( 'ofac_login_page_message', 'Connectez-vous pour accéder à votre assistant.' ) );
        } else {
            $message = esc_html__( 'Vous n\'avez pas les droits pour accéder au chatbot.', 'anythingllm-chatbot' );
        }

        $html = '<div class="ofac-access-denied">';
        $html .= '<div class="ofac-access-denied__card">';
        $html .= '<div class="ofac-access-denied__avatar">' . $avatar_html . '</div>';
        $html .= '<h2 class="ofac-access-denied__title">' . $bot_name . '</h2>';
        $html .= '<p class="ofac-access-denied__message">' . $message . '</p>';

        if ( $show_login ) {
            $login_url = wp_login_url( get_permalink() );
            $html .= '<a href="' . esc_url( $login_url ) . '" class="ofac-access-denied__login-btn">' . esc_html__( 'Se connecter', 'anythingllm-chatbot' ) . '</a>';
        }

        $html .= '<a href="' . esc_url( home_url() ) . '" class="ofac-access-denied__home-link">' . esc_html__( '← Retour à l\'accueil', 'anythingllm-chatbot' ) . '</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
```

- [ ] **Step 2: Replace the 3 bare access-denied returns in `render()`**

Replace line 46 (require_login not logged in):
```php
// Before:
return '<div class="ofac-access-denied"><p>' . esc_html__( 'Connectez-vous pour accéder au chatbot.', 'anythingllm-chatbot' ) . '</p></div>';
// After:
return $this->render_access_denied( true );
```

Replace line 52 (allowed_roles not logged in):
```php
// Before:
return '<div class="ofac-access-denied"><p>' . esc_html__( 'Connectez-vous pour accéder au chatbot.', 'anythingllm-chatbot' ) . '</p></div>';
// After:
return $this->render_access_denied( true );
```

Replace line 57 (wrong role):
```php
// Before:
return '<div class="ofac-access-denied"><p>' . esc_html__( 'Vous n\'avez pas les droits pour accéder au chatbot.', 'anythingllm-chatbot' ) . '</p></div>';
// After:
return $this->render_access_denied( false );
```

- [ ] **Step 3: Commit**

```bash
git add public/class-ofac-shortcode.php
git commit -m "feat: branded access-denied card with avatar, login button and home link"
```

---

### Task 4: Fix overflow in dedicated-page template

**Files:**
- Modify: `templates/dedicated-page.php:19-25` (add overflow rule in `<style>` block)

- [ ] **Step 1: Add `:has()` rule in the existing `<style>` block**

Add after the existing `html, body` rule (after line 25):

```css
        .ofac-dedicated-body:has(.ofac-access-denied) {
            overflow: auto;
        }
```

- [ ] **Step 2: Commit**

```bash
git add templates/dedicated-page.php
git commit -m "fix: allow scroll on dedicated page when access-denied card is shown"
```

---

### Task 5: Minify CSS/JS and final commit

**Files:**
- Modify: `assets/css/chatbot.min.css` (regenerate)
- Modify: `assets/js/chatbot.min.js` (regenerate)

- [ ] **Step 1: Check if build tooling exists**

```bash
cat package.json | grep -E '"build|"minify|"css|"js"'
```

If a minify/build script exists, run it. Otherwise minify manually via npm tooling or skip if no build process is established.

- [ ] **Step 2: Commit minified files**

```bash
git add assets/css/chatbot.min.css assets/js/chatbot.min.js
git commit -m "build: regenerate minified assets"
```

- [ ] **Step 3: Push all commits**

```bash
git push origin master
```
