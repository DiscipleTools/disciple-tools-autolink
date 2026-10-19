import {css, html, LitElement, nothing} from "lit";
import {DtBase} from "@disciple.tools/web-components";

/**
 * The main menu component. Located inside the navbar.
 */
export class AppMenu extends DtBase {
    static get styles() {
        return css`
          .menu__toggle {
            cursor: pointer;
            font-size: 2.5rem;
          }

          .menu__collapse {
            position: absolute;
            top: 100px;
            left: 0;
            right: 0;
            background-color: var(--primary-color);
            z-index: 99999;
          }

          .menu__list {
            margin: 50px 25px;
            padding: 0;
          }

          .menu__list .menu__item {
            list-style: none;
          }

          .menu__list a.menu__link {
            text-decoration: none;
            color: var(--surface-1);
            font-weight: 700;
            font-size: 14px;
            border: 1px solid var(--surface-1);
            border-radius: 4px;
            padding: 10px 20px;
            text-align: center;
            margin: 10px auto;
            display: block;
            max-width: 342px;
          }

          .menu__list a.menu__link:hover {
            background-color: var(--surface-1);
            color: var(--primary-color);
          }

          /*
           * Styled to match .menu__link.
           *
           * iOS ignores the styling entirely without appearance: none, which in
           * turn drops the native arrow, so draw one as a background image.
           *
           * A select also won't stretch to its container the way the block
           * anchors do, so it needs an explicit width. .menu__link is
           * content-box with max-width 342 + 20px padding and 1px border a
           * side, so 384px here lines the two boxes up.
           */
          .menu__list .menu__select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            box-sizing: border-box;
            display: block;
            width: 100%;
            max-width: 384px;
            margin: 10px auto;
            padding: 10px 30px;
            line-height: 1.5;
            border: 1px solid var(--surface-1);
            border-radius: 4px;
            background-color: var(--primary-color);
            color: var(--surface-1);
            font-weight: 700;
            font-size: 14px;
            text-align: center;
            text-align-last: center;
            background-image: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"%3E%3Cpath fill="%23FFFFFF" d="M5 7L2 3h6z"/%3E%3C/svg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 10px;
          }

          .menu__list .menu__select:disabled {
            opacity: 0.6;
          }

          /* The dropdown list itself is painted by the OS, not the menu. */
          .menu__list .menu__select option {
            background-color: var(--surface-1);
            color: var(--primary-color);
          }

          .menu__list a.menu__link.menu__link--logout {
            background-color: var(--surface-1);
            color: var(--primary-color);
          }

          .menu__list a.menu__link.menu__link--logout:hover {
            background-color: var(--primary-color);
            color: var(--surface-1);
          }

          .menu__backdrop {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
          }
        `;
    }

    /**
     * The component props
     */
    static get properties() {
        return {
            show: {type: Boolean, attribute: false},
            savingLocale: {type: Boolean, attribute: false},
        };
    }

    constructor() {
        super();
        this.show = false;
        this.savingLocale = false;
    }

    /**
     * The icon code.
     * @see https://iconify.design/ for more icons
     */
    get icon() {
        return this.show ? "ic:sharp-close" : "ic:sharp-menu";
    }

    /**
     * Render the component
     */
    render() {
        return html`
            <nav class="menu">
                <a
                        @click=${() => this.toggle()}
                        title="${app.translations.toggle_menu}"
                >
                    <dt-icon class="menu__toggle" icon="${this.icon}"></dt-icon>
                </a>
                ${this.renderCollapse()}
            </nav>
            ${this.renderBackdrop()}
        `;
    }

    /**
     * Render the backdrop. If clicked, it closes the menu.
     */
    renderBackdrop() {
        if (!this.show) {
            return nothing;
        }

        return html`
            <div class="menu__backdrop" @click=${this.handleBackdropClick}></div>
        `;
    }

    /**
     * Render the language picker, unless the site only ships one language.
     */
    renderLanguages() {
        const languages = window.app.languages || [];

        if (languages.length < 2) {
            return nothing;
        }

        return html`
            <li class="menu__item">
                <select
                        class="menu__select"
                        name="locale"
                        title="${app.translations.language_nav_label}"
                        aria-label="${app.translations.language_nav_label}"
                        ?disabled=${this.savingLocale}
                        @change=${this.handleLocaleChange}
                >
                    ${languages.map(
                            (language) => html`
                                <option value="${language.code}" ?selected=${language.selected}>
                                    ${language.label}
                                </option>
                            `
                    )}
                </select>
            </li>
        `;
    }

    /**
     * Persist the chosen locale, then reload.
     *
     * The app is rendered server side, so the new language only appears on the
     * next request - there is nothing to re-render client side.
     */
    async handleLocaleChange(e) {
        const locale = e.target.value;

        if (!locale || this.savingLocale) {
            return;
        }

        this.savingLocale = true;

        try {
            const response = await fetch(
                window.app.rest_base + window.magic.rest_namespace,
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-WP-Nonce": window.app.nonce,
                    },
                    body: JSON.stringify({
                        action: "switch_language",
                        locale,
                        parts: window.magic.parts,
                    }),
                }
            );
            const body = await response.json();

            if (body.success === false) {
                throw new Error(body.data?.message);
            }

            window.location.reload();
        } catch (error) {
            console.error(error);
            this.savingLocale = false;
        }
    }

    /**
     * Make sure the backdrop is the specific element clicked
     */
    handleBackdropClick(e) {
        if (e.target === e.currentTarget) {
            this.close();
        }
    }

    /**
     * Render the collapsable part of the menu
     */
    renderCollapse() {
        if (!this.show) {
            return "";
        }

        return html`
            <div class="menu__collapse">
                <ul class="menu__list">
                    <li class="menu__item">
                        <a
                                href="${app.urls.home}"
                                class="menu__link"
                                title="${app.translations.dt_nav_label}"
                        >${app.translations.dt_nav_label}</a
                        >
                    </li>
                    ${app.show_survey
                            ? html`
                                <li class="menu__item">
                                    <a
                                            href="${app.urls.survey}"
                                            class="menu__link"
                                            title="${app.translations.survey_nav_label}"
                                    >${app.translations.survey_nav_label}</a
                                    >
                                </li>
                    `
                    : nothing}
                    ${app.show_training
                            ? html`
                                <li class="menu__item">
                                    <a
                                            href="${app.urls.training}"
                                            class="menu__link"
                                            title="${app.translations.training_nav_label}"
                                    >${app.translations.training_nav_label}</a
                                    >
                                </li>
                            `
                            : nothing}
                    ${this.renderLanguages()}
                    ${app.show_logout
                            ? html`
                                <li class="menu__item">
                                    <a
                                            href="${app.urls.logout}"
                                            class="menu__link menu__link--logout"
                                            title="${app.translations.logout_nav_label}"
                                    >${app.translations.logout_nav_label}</a
                                    >
                                </li>
                            `
                            : nothing}
                </ul>
            </div>
        `;
    }

    /**
     * Toggle the menu open or closed
     */
    toggle() {
        if (this.show) {
            this.close();
        } else {
            this.open();
        }
    }

    /**
     * Close the menu
     */
    close() {
        this.show = false;
    }

    /**
     * Open the menu
     */
    open() {
        this.show = true;
    }
}

window.customElements.define("app-menu", AppMenu);
