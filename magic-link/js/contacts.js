import { html } from "lit";
import { DtBase } from "@disciple.tools/web-components";

/**
 * Fallbacks for the connection icons.
 *
 * The real ones come from the Disciple.Tools field each connection represents -
 * see Disciple_Tools_Autolink_Contacts_Controller::relationship_icons() - so
 * that an admin who changes a field's icon changes these too. These stand in
 * only when a field carries no icon at all.
 */
const FALLBACK_ICONS = {
  coaching: "mdi:account-supervisor",
  assigned: "mdi:account-check",
  leader: "mdi:crown",
  member: "mdi:church",
};

/**
 * Whether a milestone has been reached, said without words.
 *
 * The alternative was a translated "reached" / "not reached", which would have
 * meant two more strings to carry and a spell on every site where the
 * translation had not caught up yet. A check and a cross say it in any language.
 */
const REACHED = "\u2713"; // CHECK MARK
const NOT_REACHED = "\u2715"; // MULTIPLICATION X - better covered than U+2717

/**
 * Whether this browser has the popover API.
 *
 * Around 94% do (2026-10). Where it is missing the milestone marks stay plain, keeping
 * their `title`, which is what they had before - a worse deal on a touch screen,
 * but not a broken one.
 */
const SUPPORTS_POPOVER =
  typeof HTMLElement !== "undefined" &&
  Object.prototype.hasOwnProperty.call(HTMLElement.prototype, "popover");

/** The milestone label currently showing, if any. */
let openTip = null;

function hideOpenTip() {
  // hidePopover() throws on a popover that is already closed, and `toggle` is
  // queued rather than synchronous, so there is a moment where our reference
  // outlives the open state. Ask the element instead of trusting the variable.
  if (openTip && openTip.matches(":popover-open")) {
    openTip.hidePopover();
  }
}

/**
 * Put the label beside the mark that opened it.
 *
 * CSS anchor positioning would do this declaratively, but it is only partly
 * implemented in Chrome and arrived in Firefox 147, so it is not a safe thing to
 * rest on yet - hence the arithmetic. The popover is in the top layer, so these
 * are viewport coordinates and no ancestor can clip it.
 *
 * It is placed on `toggle` rather than `beforetoggle` because the element has no
 * dimensions to measure until it is actually showing; it starts transparent and
 * fades in once placed, so nobody sees the frame where it sits in the middle of
 * the screen where the user agent puts it by default.
 */
function placeTip(event) {
  const tip = event.target;

  if (event.newState !== "open") {
    if (openTip === tip) {
      openTip = null;
    }
    tip.classList.remove("is-placed");
    return;
  }

  openTip = tip;

  const mark = tip.previousElementSibling;

  if (!mark) {
    return;
  }

  const anchor = mark.getBoundingClientRect();
  const box = tip.getBoundingClientRect();
  const gap = 8;

  // Centred on the mark, then pulled back inside the viewport - the first and
  // last milestone in the row sit close enough to the edge to overhang it.
  const left = Math.max(
    gap,
    Math.min(
      anchor.left + anchor.width / 2 - box.width / 2,
      window.innerWidth - box.width - gap
    )
  );

  // Above by preference, below when the card is near the top of the screen.
  const above = anchor.top - box.height - gap;
  const top = above < gap ? anchor.bottom + gap : above;

  tip.style.left = `${left}px`;
  tip.style.top = `${top}px`;
  tip.classList.add("is-placed");

  // Nothing re-anchors it as the page moves, and a label stranded away from its
  // icon reads as a bug, so the first scroll puts it away.
  window.addEventListener("scroll", hideOpenTip, {
    once: true,
    passive: true,
    capture: true,
  });
}

if (SUPPORTS_POPOVER) {
  /**
   * Touch anywhere to put the label away - including on the label itself.
   *
   * A popover with no explicit type light-dismisses on an outside click of its
   * own accord, but two gaps make that not enough. It does not count a touch on
   * the popover itself, which is the first place a thumb goes; and iOS Safari
   * between 17.0 and 18.2 - shipping the API, and the single most likely browser
   * to meet this - does not dismiss on an outside touch at all. So dismissal is
   * handled here rather than left to the platform.
   *
   * The one touch left alone is the mark that opened it: the browser closes a
   * popover when its own invoker is pressed again, and closing it here first
   * would turn that second press into a re-open.
   */
  document.addEventListener(
    "pointerdown",
    (event) => {
      if (!openTip) {
        return;
      }

      const invoker = event.target.closest?.("[popovertarget]");

      if (invoker && invoker.getAttribute("popovertarget") === openTip.id) {
        return;
      }

      hideOpenTip();
    },
    true
  );
}

/**
 * @class Contacts
 *
 * The people around this leader, as cards matching the churches tab.
 *
 * Rendered into the light DOM like <app-churches>, so the app stylesheet reaches
 * the cards and the two tabs stay in step.
 */
export class Contacts extends DtBase {
  static get properties() {
    return {
      ...super.properties,
      contacts: { type: Array },
      translations: { type: Object },
      relationshipIcons: { type: Object },
      milestoneOptions: { type: Array },
    };
  }

  constructor() {
    super();
    this.contacts = [];
    this.translations = {};
    this.relationshipIcons = {};
    this.milestoneOptions = [];
  }

  createRenderRoot() {
    return this;
  }

  render() {
    const { contacts, translations } = this;

    if (!contacts || !contacts.length) {
      return html`<p class="contacts__empty">${translations.empty}</p>`;
    }

    return html`
      <div class="contacts__list">
        ${contacts.map((contact) => this.renderContact(contact))}
      </div>
    `;
  }

  renderContact(contact) {
    return html`
      <contact-tile
        class="contact"
        title="${contact.name}"
        key="contact-${contact.id}"
      >
        ${this.renderLoginMark(contact)} ${this.renderMenu(contact)}
        ${this.renderConnections(contact)} ${this.renderMilestones(contact)}
        ${this.renderActions(contact)}
      </contact-tile>
    `;
  }

  /**
   * The one mark that is ours rather than a field's: there is no Disciple.Tools
   * field behind "this person has their own login".
   *
   * Slotted in immediately after the name, so it reads as a fact about this
   * person rather than an action sitting in the corner.
   */
  renderLoginMark(contact) {
    const { translations } = this;

    if (!contact.has_login) {
      return null;
    }

    return html`
      <span
        class="contact__login"
        slot="after-name"
        title="${translations.has_login}"
        aria-label="${translations.has_login}"
      >
        <dt-icon icon="mdi:account-lock"></dt-icon>
      </span>
    `;
  }

  /**
   * How you know this person: a badge per kind, then the churches those came
   * from written out underneath.
   */
  renderConnections(contact) {
    const connections = contact.relationships || [];

    if (!connections.length) {
      return null;
    }

    const details = connections.filter((connection) => connection.detail);

    return html`
      <div class="contact__badges">
        ${connections.map(
          (connection) => html`
            <span class="contact__badge contact__badge--${connection.type}">
              ${this.renderConnectionIcon(connection.type)}
              <span>${connection.label}</span>
            </span>
          `
        )}
      </div>
      ${details.length
        ? html`
            <ul class="contact__churches">
              ${details.map(
                (connection) =>
                  html`<li class="contact__church">${connection.detail}</li>`
              )}
            </ul>
          `
        : null}
    `;
  }

  /**
   * A field's own icon where it has one, its font icon where it has that
   * instead, and our fallback where it has neither.
   *
   * @param {string} type
   */
  renderConnectionIcon(type) {
    const field = this.relationshipIcons[type] || {};

    if (field.icon) {
      return html`
        <img
          class="contact__badge-icon"
          src="${field.icon}"
          alt=""
          width="16"
          height="16"
        />
      `;
    }

    return html`
      <dt-icon
        class="contact__badge-icon"
        icon="${field.font_icon || FALLBACK_ICONS[type] || "mdi:account"}"
      ></dt-icon>
    `;
  }

  /**
   * How far along this person is: every milestone the field defines, the ones
   * they have reached lit and the rest dimmed.
   *
   * The unreached ones stay on the card deliberately. A row that only showed what
   * had been reached would read the same whether someone had passed one milestone
   * out of two or one out of nine; keeping the whole row makes the gap the thing
   * you see. This is the reading view - setting them comes later.
   */
  renderMilestones(contact) {
    const { milestoneOptions, translations } = this;

    if (!milestoneOptions || !milestoneOptions.length) {
      return null;
    }

    const reached = contact.milestones || [];

    return html`
      <ul
        class="contact__milestones"
        aria-label="${translations.milestones || ""}"
      >
        ${milestoneOptions.map((milestone) =>
          this.renderMilestone(
            contact,
            milestone,
            reached.includes(milestone.key)
          )
        )}
      </ul>
    `;
  }

  /**
   * One milestone, and the label that says which one it is.
   *
   * The row is icons alone, which on a desktop a `title` explains and on a phone
   * explains nothing - there is no hover to tell you that the third circle means
   * "States Belief". So the mark is a button that opens its own label, and the
   * label is a popover: it draws in the top layer, so it is not clipped by the
   * card, and the browser keeps only one open at a time, so touching a second
   * mark replaces the first label rather than stacking on it.
   *
   * `title` stays on the mark for the pointer users who were already served by
   * it, and is the whole story on a browser without popovers.
   */
  renderMilestone(contact, milestone, isReached) {
    const label = `${milestone.label} ${isReached ? REACHED : NOT_REACHED}`;
    const mark = `contact__milestone-mark contact__milestone-mark--${
      isReached ? "on" : "off"
    }`;

    if (!SUPPORTS_POPOVER) {
      return html`
        <li class="contact__milestone">
          <span class="${mark}" title="${label}" aria-label="${label}">
            ${this.renderMilestoneIcon(milestone)}
          </span>
        </li>
      `;
    }

    const tipId = `milestone-${contact.id}-${milestone.key}`;

    return html`
      <li class="contact__milestone">
        <button
          type="button"
          class="${mark}"
          popovertarget="${tipId}"
          title="${label}"
          aria-label="${label}"
        >
          ${this.renderMilestoneIcon(milestone)}
        </button>
        <div
          id="${tipId}"
          popover
          class="contact__milestone-tip"
          @toggle=${placeTip}
        >
          ${label}
        </div>
      </li>
    `;
  }

  /**
   * A milestone's own icon, drawn whichever way Disciple.Tools holds it: an image
   * address, or a named font icon. The controller has already put a font icon
   * into the name <dt-icon> expects, so there is nothing to parse here.
   *
   * @param {{icon: string, font_icon: string, label: string}} milestone
   */
  renderMilestoneIcon(milestone) {
    if (milestone.icon) {
      return html`
        <img
          class="contact__milestone-icon"
          src="${milestone.icon}"
          alt=""
          width="20"
          height="20"
        />
      `;
    }

    return html`
      <dt-icon
        class="contact__milestone-icon"
        icon="${milestone.font_icon || "mdi:check-decagram"}"
      ></dt-icon>
    `;
  }

  /**
   * One button, one label.
   *
   * Where the link goes still depends on whether this person has a login - their
   * own magic link if not, the ordinary sign-in address if so, because a magic
   * key into an account with a password would hand over everything that account
   * can reach. That is our distinction to keep, not theirs to read.
   */
  renderActions(contact) {
    const { translations } = this;

    if (!contact.link && !contact.can_link) {
      return null;
    }

    // A contact link is issued on the press, not when the list is drawn, so it
    // runs for the configured validity from when it is handed over.
    const source = contact.can_link ? () => this.issueLink(contact) : null;

    return html`
      <div class="contact__actions">
        <app-copy-link
          value="${contact.link || ""}"
          .source=${source}
          label="${translations.copy_link}"
          copiedLabel="${translations.copied}"
          errorLabel="${translations.copy_failed}"
        ></app-copy-link>
        ${this.renderLinkStatus(contact)}
      </div>
    `;
  }

  /**
   * What the link just copied is good for, or that the last one has lapsed.
   *
   * Beside the button rather than in it: the button's own confirmation goes
   * back after a moment, and this is worth reading after that.
   */
  renderLinkStatus(contact) {
    const { translations } = this;

    if (contact.link_copied) {
      if (!contact.link_expires_label) {
        return null;
      }

      return html`<span class="contact__link-status">
        ${translations.link_expires.replace("%s", contact.link_expires_label)}
      </span>`;
    }

    if (contact.link_expired) {
      return html`<span
        class="contact__link-status contact__link-status--expired"
      >
        ${translations.link_expired}
      </span>`;
    }

    return null;
  }

  /**
   * Ask the server for a contact's link, minting or extending it.
   *
   * @param {object} contact the card's row, updated with the new expiry
   * @returns {Promise<string>}
   */
  async issueLink(contact) {
    const response = await fetch(
      window.app.rest_base + window.magic.rest_namespace,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": window.app.nonce,
        },
        body: JSON.stringify({
          action: "contact_link",
          parts: window.magic.parts,
          contact_id: contact.id,
        }),
      }
    );
    const body = await response.json().catch(() => null);

    if (!response.ok || !body || !body.link) {
      throw new Error((body && body.message) || `HTTP ${response.status}`);
    }

    contact.link_copied = true;
    contact.link_expired = false;
    contact.link_expires_label = body.expires_label || "";
    this.requestUpdate();

    return body.link;
  }

  /**
   * The cog in the corner, holding what can be done to this person.
   *
   * Same component the churches list uses, so one gesture opens the menu on
   * either tab. View is only there for someone signed in: it frames the record's
   * own Disciple.Tools page, which a magic link cannot open - so the controller
   * hands over no link at all rather than one that leads to a sign-in screen.
   */
  renderMenu(contact) {
    const { translations } = this;

    if (!contact.edit_link && !contact.view_link) {
      return null;
    }

    return html`
      <app-card-menu>
        ${contact.view_link
          ? html`
              <dt-button context="primary" href="${contact.view_link}">
                ${translations.view}
              </dt-button>
            `
          : null}
        ${contact.edit_link
          ? html`
              <dt-button context="primary" href="${contact.edit_link}">
                ${translations.edit}
              </dt-button>
            `
          : null}
      </app-card-menu>
    `;
  }
}

window.customElements.define("app-contacts", Contacts);
