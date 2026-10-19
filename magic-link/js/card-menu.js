import {DtBase} from "@disciple.tools/web-components";
import {css, html, nothing} from "lit";
import {classMap} from "lit/directives/class-map.js";
import {AppCollapse} from "./collapse";

export class AppCardMenu extends AppCollapse {
    constructor() {
        super();
        this.openIcon = "mdi:cog-outline";
        this.closeIcon = "mdi:cog";
    }

    static get properties() {
        return {
            opened: {type: Boolean, reflect: true},
            openIcon: {type: String},
            closeIcon: {type: String},
        };
    }

    static get styles() {
        return css`
          :host {
            color: currentcolor;
            display: block;
          }

          .collapse__icon {
            font-size: 1.2rem;
            display: flex;
            justify-content: center;
            position: absolute;
            top: 6px;
            inset-inline-end: 8px;
            z-index: 0;
            color: var(--primary-color);
            z-index: 3
          }

          .card__menu {
            position: absolute;
            top: 34px;
            inset-inline-end: 8px;
            z-index: 1;
            min-width: 200px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: end;
          }

          .menu__overlay {
            position: absolute;
            background-color: white;
            opacity: 0.7;
            top: 0;
            right: 0;
            bottom: 0;
            left 0;
            width: 100%;
            height: 100%;
          }
        `;
    }

    render() {
        return html`
            <div class="menu__wrapper">
                <div class="card__menu">${this.renderContent()}</div>
                ${this.renderIcon()} ${this.renderOverlay()}
            </div>
        `;
    }

    renderOverlay() {
        if (this.opened) {
            return html`
                <div
                        class="menu__overlay"
                        @click=${() => this._close()}
                ></div>`;
        }
    }

    renderContent() {
        if (this.opened) {
            return html`
                <slot></slot>`;
        }
        return nothing;
    }
}

window.customElements.define("app-card-menu", AppCardMenu);
