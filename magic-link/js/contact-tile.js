import { html, css } from "lit";
import { DtTile } from "@disciple.tools/web-components";

/**
 * The card shell for one person.
 *
 * The churches tab's card (see church-tile.js), with the heading reading from the
 * inline start rather than centred, because a person's name is read, not admired.
 *
 * The `after-name` slot sits immediately next to the name rather than at the far
 * end of the line, so that a mark about the person reads as belonging to them
 * rather than floating in the corner.
 */
export class ContactTile extends DtTile {
  constructor() {
    super();
  }

  static get styles() {
    return [
      super.styles,
      css`
        .section-header {
          display: flex;
          /* DtTile centres its heading; the name and its mark read from the
             start, and the title no longer stretches to hide that. */
          justify-content: flex-start;
          align-items: center;
          gap: 0.4rem;
          text-align: start;
          margin: 0 0 0.6rem;
        }

        /* Shrinks rather than grows, so the mark beside it stays against the name. */
        .contact__title {
          flex: 0 1 auto;
          min-width: 0;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        section {
          position: relative;
        }
      `,
    ];
  }

  static get properties() {
    return {
      ...super.properties,
    };
  }

  renderHeading() {
    return html`
      <h3 class="section-header">
        <span class="contact__title">${this.title}</span>
        <slot name="after-name"></slot>
      </h3>
    `;
  }

  render() {
    return html`
      <section>
        ${this.renderHeading()}
        <div class="section-body ${this.collapsed ? "collapsed" : null}">
          <slot></slot>
        </div>
      </section>
    `;
  }
}

window.customElements.define("contact-tile", ContactTile);
