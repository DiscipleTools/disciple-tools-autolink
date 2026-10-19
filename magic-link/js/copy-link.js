import { css, html } from "lit";
import { DtBase } from "@disciple.tools/web-components";

/**
 * A link, collapsed to a button.
 *
 * <dt-copy-text> shows the whole URL in a field, which is a lot of card for
 * something nobody reads - a magic key is 64 characters of noise. This is the
 * same gesture in one button: press it, the link is on the clipboard, the icon
 * confirms it for a moment and goes back.
 *
 * Give it a `value` to copy, or a `source` - a function returning a promise of
 * the text - when the link only comes into being on the press, as contact links
 * do so that each one runs from when it is handed over.
 */
export class AppCopyLink extends DtBase {
  static get properties() {
    return {
      value: { type: String },
      label: { type: String },
      copiedLabel: { type: String },
      title: { type: String },
      icon: { type: String },
      errorLabel: { type: String },
      source: { attribute: false },
      copied: { type: Boolean, attribute: false },
      failed: { type: Boolean, attribute: false },
      busy: { type: Boolean, attribute: false },
    };
  }

  constructor() {
    super();
    this.value = "";
    this.label = "";
    this.copiedLabel = "";
    this.icon = "mdi:link-variant";
    this.errorLabel = "";
    this.source = null;
    this.copied = false;
    this.failed = false;
    this.busy = false;
    this._resetTimer = null;
  }

  static get styles() {
    return css`
      :host {
        display: inline-block;
      }

      .copy {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.7rem;
        border: 1px solid var(--primary-color, #2c5364);
        border-radius: 4px;
        background: none;
        color: var(--primary-color, #2c5364);
        font: inherit;
        font-size: 0.8125rem;
        font-weight: 700;
        line-height: 1.4;
        cursor: pointer;
      }

      .copy:hover,
      .copy:focus-visible {
        background: var(--primary-color, #2c5364);
        color: var(--surface-1, #fff);
      }

      .copy--copied {
        border-color: var(--success-color, #4caf50);
        color: var(--success-color, #4caf50);
      }

      .copy--failed {
        border-color: var(--alert-color, #cc4b37);
        color: var(--alert-color, #cc4b37);
      }

      .copy[disabled] {
        cursor: progress;
        opacity: 0.7;
      }

      .copy--copied:hover,
      .copy--copied:focus-visible {
        background: var(--success-color, #4caf50);
        color: var(--surface-1, #fff);
      }

      dt-icon {
        font-size: 1rem;
        display: flex;
      }
    `;
  }

  disconnectedCallback() {
    super.disconnectedCallback();
    clearTimeout(this._resetTimer);
  }

  render() {
    const {
      copied,
      failed,
      busy,
      label,
      copiedLabel,
      errorLabel,
      icon,
      title,
    } = this;
    let text = label;
    let shownIcon = icon;

    if (copied) {
      text = copiedLabel || label;
      shownIcon = "mdi:check";
    } else if (failed) {
      text = errorLabel || label;
      shownIcon = "mdi:alert-circle-outline";
    }

    return html`
      <button
        class="copy ${copied ? "copy--copied" : ""} ${failed
          ? "copy--failed"
          : ""}"
        type="button"
        title="${title || label}"
        aria-label="${title || label}"
        ?disabled=${busy}
        @click=${this.handleClick}
      >
        <dt-icon icon="${shownIcon}"></dt-icon>
        ${text ? html`<span>${text}</span>` : null}
      </button>
    `;
  }

  async handleClick() {
    if (this.busy || (!this.value && !this.source)) {
      return;
    }

    this.busy = true;
    this.failed = false;

    try {
      if (this.source) {
        await this.copyPending(this.source());
      } else {
        await this.copy(this.value);
      }
      this.flash("copied", 2000);
    } catch (error) {
      console.error(error);
      this.flash("failed", 3000);
    } finally {
      this.busy = false;
    }
  }

  /**
   * Show a state for a moment.
   *
   * @param {"copied"|"failed"} state
   * @param {number} ms
   */
  flash(state, ms) {
    this.copied = state === "copied";
    this.failed = state === "failed";
    clearTimeout(this._resetTimer);
    this._resetTimer = setTimeout(() => {
      this.copied = false;
      this.failed = false;
    }, ms);
  }

  /**
   * Copy text that is still on its way from the server.
   *
   * Safari only lets a page write to the clipboard during the click itself, and
   * awaiting a request first uses that up. Handing the clipboard a promise
   * inside a ClipboardItem keeps the write attached to the click while the text
   * arrives. Where that is not supported, wait and copy the ordinary way.
   *
   * @param {Promise<string>} pending
   */
  async copyPending(pending) {
    if (
      navigator.clipboard &&
      navigator.clipboard.write &&
      window.isSecureContext &&
      typeof window.ClipboardItem !== "undefined"
    ) {
      try {
        await navigator.clipboard.write([
          new window.ClipboardItem({
            "text/plain": pending.then(
              (text) => new Blob([text], { type: "text/plain" })
            ),
          }),
        ]);
        return;
      } catch (error) {
        // A failed request fails here too - let that through rather than
        // asking for the link a second time.
        await pending;
      }
    }

    return this.copy(await pending);
  }

  /**
   * navigator.clipboard needs a secure context, and these pages are opened from
   * links people were sent - which can mean plain http on a local network. Fall
   * back to the old selection trick rather than failing silently.
   *
   * @param {string} text
   */
  async copy(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }

    const field = document.createElement("textarea");
    field.value = text;
    field.setAttribute("readonly", "");
    field.style.position = "absolute";
    field.style.left = "-9999px";
    document.body.appendChild(field);
    field.select();

    try {
      document.execCommand("copy");
    } finally {
      document.body.removeChild(field);
    }
  }
}

window.customElements.define("app-copy-link", AppCopyLink);
