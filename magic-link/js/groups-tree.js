import {html, css, LitElement} from "lit";
import {classMap} from "lit/directives/class-map.js";
import Sortable from 'sortablejs';

/**
 * A component that renders a sortable tree of groups.
 */
export class ChurchTile extends LitElement {
    static get properties() {
        return {
            title: {type: String},
            unassignedTitle: {type: String},
            unassignedTip: {type: String},
            keyTitle: {type: String},
            assignedLabel: {type: String},
            coachedLabel: {type: String},
            leadingLabel: {type: String},
            generationLabel: {type: String},
            tree: {type: Object, reflect: true},
            unassignedTree: {type: Object, reflect: true},
            parents: {type: Object, reflect: true},
            titles: {type: Object, reflect: true},
            loading: {type: Boolean, reflect: true},
            endpoint: {type: String},
            error: {type: String},
            noGroupsMessage: {type: String},
        };
    }

    sortableInstances = []

    /* Replacements for the @queryAll / @query decorators */
    get sortables() {
        return this.renderRoot.querySelectorAll('.groups--sortable');
    }

    get groupLists() {
        return this.renderRoot.querySelectorAll('.groups');
    }

    get groupGenerationIcons() {
        return this.renderRoot.querySelectorAll('#tree .group__generation');
    }

    get unassignedSection() {
        return this.renderRoot.querySelector('#unassigned');
    }

    constructor() {
        super();
        this.title = 'Church Tree';
        this.unassignedTitle = 'Unassigned Churches';
        this.unassignedTip = 'Move these churches to the Church Tree to assign them to a group.';
        this.keyTitle = 'Key';
        this.assignedLabel = 'Churches you are assigned to';
        this.coachedLabel = 'Churches you coach';
        this.leadingLabel = 'Churches you lead';
        this.generationLabel = 'Generation Number';
        this.tree = [];
        this.unassignedTree = [];
        this.parents = {};
        this.titles = {};
        this.loading = true;
        this.endpoint = '';
        this.error = '';
        this.noGroupsMessage = 'No churches found.';
    }

    static get styles() {
        return css`
          :host {
            justify-content: center;
            display: block;
            width: 100%;
            --tree-spacing: 10px;
          }

          #tree,
          #unassigned-tree {
            margin-top: 0;
            margin-bottom: calc(var(--tree-spacing) * 2);
            overflow-y: hidden;
            overflow-x: auto;
          }

          p {
            font-weight: normal;
          }

          ul {
            list-style: none;
            position: relative;
            padding-inline-start: calc(var(--tree-spacing) * 2);
          }

          .groups {
            position: relative;
          }

          .section__inner {
            grid-column: 1 / -1;
          }

          li {
            position: relative;
          }

          li:first-child {
            border-top: none;
          }

          li:last-child {
            border-bottom: none;
          }

          .groups-tree {
            width: 100%;
          }

          .group__title {
            display: block;
            color: #2C5364;
            max-width: 100%;
            white-space: nowrap;
          }

          .group__body {
            display: flex;
            align-items: center;
            padding-top: calc(var(--tree-spacing) * 2);
          }

          #tree > .group:first-child > .group__body,
          #unassigned-tree > .group:first-child > .group__body {
            padding-top: 0;
          }

          #tree > .group > .group__body > .group__tag:before,
          #tree > .group > .group__body > .group__tag:after,
          #unassigned-tree > .group > .group__body > .group__tag:before,
          #unassigned-tree > .group > .group__body > .group__tag:after {
            display: none;
          }

          .tree__spinner {
            display: flex;
            align-items: center;
            justify-content: center;
            grid-column: 1 / -1;
          }

          #tree,
          #unassigned-tree {
            padding-inline-start: 0;
          }

          #unassigned.unassigned--empty {
            display: none;
          }

          .group {
            width: fit-content;
          }

          .group__tag {
            background-color: #E2E2E2;
            display: flex;
            align-items: center;
            padding: calc(var(--tree-spacing) * .5) calc(var(--tree-spacing) * 1.5) calc(var(--tree-spacing) * .5) calc(var(--tree-spacing) * 1.5);
            border-start-start-radius: 0;
            border-start-end-radius: 5px;
            border-end-end-radius: 5px;
            border-end-start-radius: 0;
            position: relative;
            border: solid 1px #afc1cc;
            border-inline-start-color: #2C5364;
            min-height: calc(var(--tree-spacing) * 3);
            gap: var(--tree-spacing);
          }

          .group__tag:before {
            content: '';
            display: block;
            width: calc(var(--tree-spacing) * 2);
            height: 1px;
            background-color: #2C5364;
            position: absolute;
            top: 50%;
            inset-inline-start: calc(var(--tree-spacing) * -2);
            transform: translateY(1px);
          }

          .group__tag:after {
            content: '';
            display: block;
            width: 1px;
            border-inline-start: 1px inset #2C5364;
            inset-inline-start: calc(var(--tree-spacing) * -2 - 1px);
            top: calc(var(--tree-spacing) * -2 - 2px);
            bottom: 0;
            position: absolute;
          }

          .group:not(:last-child) > .group__children:before {
            content: '';
            display: block;
            width: 1px;
            border-inline-start: 1px inset #2C5364;
            inset-inline-start: calc(var(--tree-spacing) * -2);
            top: -1px;
            bottom: -2px;
            position: absolute;
          }

          .group:last-child > .group__body > .group__tag:after {
            bottom: calc(100% - calc(var(--tree-spacing) * 2 + 2px));
          }

          .group--assigned > .group__body > .group__tag {
            background-color: #b3e3ae;
          }

          .group__label,
          .group__handle {
            display: flex;
            align-items: center;
            pointer-events: none;
          }

          .group__handle {
            padding-top: calc(var(--tree-spacing) * .5);
            margin-inline-start: calc(var(--tree-spacing) * -1.5);
            width: var(--tree-spacing);
          }

          .group__icons {
            display: flex;
            gap: calc(var(--tree-spacing) * .5);
            color: #2C5364;
            font-weight: lighter;
            align-items: center;
            justify-content: center;
          }

          .group__icons dt-icon {
            transform: translateY(3px);
          }

          .key__generation,
          .group__generation:not(:empty) {
            height: var(--tree-spacing);
            width: var(--tree-spacing);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: solid 1px #2C5364;
            font-size: calc(var(--tree-spacing) * .9);
            line-height: calc(var(--tree-spacing));
            font-weight: lighter;
          }

          dt-icon {
            color: #2C5364;
          }

          .unassigned__tip {
            margin-bottom: calc(var(--tree-spacing) * 2.5);
          }
          
          .group__icon {
            margin-inline-start: var(--tree-spacing);
            display: block;
            transform: translateY(2px);
          }

          .tree__key td {
            padding: 0 var(--tree-spacing);
          }

          .key {
            border: solid 1px #afc1cc;
            border-inline-start-color: #2C5364;
            border-start-start-radius: 0;
            border-start-end-radius: 5px;
            border-end-end-radius: 5px;
            border-end-start-radius: 0;
            background-color: #E2E2E2;
          }

          .key--assigned {
            background-color: #b3e3ae;
          }
        `;
    }

    /**
     * When the component is first connected to the DOM, load the tree.
     */
    connectedCallback() {
        super.connectedCallback();
        this.load();
    }

    /**
     * When the component is disconnected from the DOM, destroy the sortable instances.
     */
    disconnectedCallback() {
        super.disconnectedCallback();
        this.sortableInstances.forEach((instance) => {
            instance.destroy();
        });
    }

    /**
     * Load the tree from the server.
     * @returns {Promise<void>}
     */
    async load() {
        this.loading = true
        this.error = ''
        let result
        try {
            result = await this.fetch('tree')
        } catch (e) {
            this.loading = false
            this.error = e.message
            return;
        }

        let {tree, parent_list, title_list} = await result.json()
        let unassignedChildTree = tree.filter((group) => group.id === 'u')
        if (unassignedChildTree.length) {
            unassignedChildTree = unassignedChildTree[0].children
        }
        tree = tree.filter((group) => group.id !== 'u')

        this.tree = tree
        this.parents = parent_list
        this.titles = title_list
        this.loading = false
        this.unassignedTree = unassignedChildTree
        setTimeout(this.initSortable.bind(this), 1)
        setTimeout(this.applyDomTweaks.bind(this), 1)
    }

    /**
     * Initialize the sortable instances.
     * @see https://github.com/SortableJS/Sortable#event-object-demo
     */
    initSortable() {
        this.sortables.forEach((sortable) => {
            this.sortableInstances.push(
                new Sortable(sortable, {
                    group: {
                        name: 'tree',
                        pull: true,
                        put: true
                    },
                    handle: '.group__tag',
                    animation: 500,
                    fallbackOnBody: true,
                    swapThreshold: .11,
                    onEnd: this.handleDrop.bind(this),
                    onChoose: this.applyDomTweaks.bind(this),
                    onUnchoose: this.applyDomTweaks.bind(this),
                    onStart: this.applyDomTweaks.bind(this),
                    onAdd: this.applyDomTweaks.bind(this),
                    onUpdate: this.applyDomTweaks.bind(this),
                    onRemove: this.applyDomTweaks.bind(this),
                    onFilter: this.applyDomTweaks.bind(this),
                    onClone: this.applyDomTweaks.bind(this),
                    onCharge: this.applyDomTweaks.bind(this),
                    sort: false,
                    delay: 10
                })
            )
        });
    }

    /**
     * Fetch data from the server.
     * @param action
     * @param data
     * @returns {Promise<Response>}
     */
    fetch(action, data) {
        return fetch(this.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
                'X-WP-Nonce': window.app.nonce
            },
            body: JSON.stringify({
                action,
                parts: window.magic.parts,
                data
            })
        })
    }

    /**
     * Render the component.
     * @returns {TemplateResult<1>}
     */
    render() {
        const {tree, loading} = this;
        if (!tree.length && !loading) {
            return html`
                <dt-alert context="info">
                    ${this.noGroupsMessage}
                </dt-alert>
            `
        }
        return html`
            <div class="tree">
                ${this.renderTree()}
                ${this.renderUnassignedTree()}
                ${this.renderKey()}
        `
    }

    /**
     * Render the tree.
     * @returns {TemplateResult<1>}
     */
    renderTree() {
        const {loading, tree, error, syncing, unassignedTree, title} = this;

        if (error) {
            return html`
                <dt-alert context="error" dismissable>${error}</dt-alert>
            `
        }

        return html`
            <dt-tile title="${title}">
                ${loading ? html`
                    <div class="tree__spinner">
                        <dt-spinner></dt-spinner>
                    </div>` : ''}
                <div class="section__inner">
                    <ul class="groups groups--sortable" id="tree">
                        ${tree.map(group => this.renderGroup(group))}
                    </ul>
                </div>
            </dt-tile>
        `
    }

    /**
     * Render the unassigned tree. Unassigned churches are churches that are not assigned to a parent group.
     * @returns {TemplateResult<1>}
     */
    renderUnassignedTree() {
        const {unassignedTree, unassignedTitle, unassignedTip, loading} = this;

        return html`
            <dt-tile title="${unassignedTitle}" id="unassigned">
                <div class="section__inner">
                    <dt-alert class="unassigned__tip" context="success" outline icon="" dismissable>
                        ${unassignedTip}
                    </dt-alert>
                    <ul class="groups groups--sortable" id="unassigned-tree">
                        ${unassignedTree.map(group => this.renderGroup(group))}
                    </ul>
                </div>
            </dt-tile>
        `
    }

    renderKey() {
        const {keyTitle, assignedLabel, coachedLabel, leadingLabel, generationLabel, loading} = this;

        if (loading) {
            return null
        }

        return html`
            <dt-tile title="${keyTitle}">
                <div class="section__inner">
                    <table class="tree__key">
                        <tr>
                            <td class="key key--assigned">
                            </td>
                            <td>
                                <p>
                                    ${assignedLabel}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td class="key key--coached">
                            </td>
                            <td>
                                <p>
                                    ${coachedLabel}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td class="key key--leading">
                                <dt-icon icon="ph:user-bold" size="15px"></dt-icon>
                            </td>
                            <td>
                                <p>
                                    ${leadingLabel}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td class="key key--generation">
                                <div class="key__generation">
                                    1
                                </div>
                            </td>
                            <td>
                                <p>
                                    ${generationLabel}
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </dt-tile>
            </div>`
    }

    /**
     * Render a group.
     * @param id
     * @param children
     * @param name
     * @param assigned
     * @param leading
     * @param isSortable
     * @returns {TemplateResult<1>}
     */
    renderGroup({id, children, name, assigned, leading, coaching}, isSortable = true) {
        return html`
            <li data-id="${id}"
                data-assigned="${assigned}"
                class="${
                        classMap({
                            'group': true,
                            'group--assigned': assigned,
                            'group--leading': leading,
                            'group--coaching': coaching,
                        })
                }"
            >
                <div class="group__body">
                    <div class="group__tag">
                        ${isSortable ? html`
                            <div class="group__handle">
                                <dt-icon icon="clarity:drag-handle-line" size="20px"
                                         class="group__icon--handle"></dt-icon>
                            </div>` : null}
                        <span class="group__generation"></span>
                        <label class="group__title">${name}</label>
                        <div class="group__icons">
                            ${assigned ? html`
                                <dt-icon icon="ph:user-bold" size="15px" class="group__icon--assigned"></dt-icon>
                            ` : null}
                        </div>
                    </div>
                    <div style="flex-grow: 1"></div>
                </div>
                ${assigned ? this.renderChildren(children) : null}
            </li>
        `
    }

    /**
     * Render the children of a group.
     * @param children
     * @returns {TemplateResult<1>}
     */
    renderChildren(children) {
        return html`
            <ul class="${classMap({
                "groups groups--sortable group__children": true
            })}">
                ${children ? children.map(group => this.renderGroup(group)) : null}
            </ul>
        `
    }

    /**
     * Handle a SortableJS drop event.
     * @param event
     * @see
     */
    handleDrop(event) {
        const {assignedError, tree} = this
        const {oldIndex, newIndex, item, to, from} = event
        const id = item.dataset.id
        const newParent = to.closest('li')
        const newParentId = newParent ? newParent.dataset.id : 'root'
        const oldParent = from.closest('li')
        const oldParentId = oldParent ? oldParent.dataset.id : 'root'

        if (!this.validateDrop(event)) {
            const nextElement = from.children[oldIndex + 1]
            if (!nextElement) {
                console.log(from)
                from.appendChild(item)
            } else {
                from.insertBefore(item, nextElement)
            }
            this.applyDomTweaks()
            return;
        }

        this.saveParentConnection(id, oldParentId, newParentId)
        setTimeout(this.applyDomTweaks.bind(this), 1)
    }

    /**
     * Find a group in the tree.
     * @param id
     * @param tree
     * @returns {*}
     */
    findGroup(id, tree = false) {
        if (!tree) {
            tree = this.tree
        }
        return tree.reduce((match, group) => {
            if (match) {
                return match;
            }

            let isMatch = group.id === parseInt(id)

            if (isMatch) {
                return group
            }

            if (group.children) {
                return this.findGroup(id, group.children)
            }

            return false;
        }, false)
    }

    /**
     * Validate a drop event.
     * @param oldIndex
     * @param newIndex
     * @param item
     * @param to
     * @param from
     * @returns {boolean}
     */
    validateDrop({oldIndex, newIndex, item, to, from}) {
        const id = item.dataset.id

        if (from.id === 'unassigned-tree') {
            if (to.id === 'tree') {
                return false;
            }
            return true;
        }

        const group = this.findGroup(id)

        if (!group) {
            return false;
        }

        if (!group.assigned && to.id === 'tree') {
            return false;
        }

        return true;
    }

    /**
     * Apply DOM tweaks to the tree.
     */
    applyDomTweaks() {
        this.groupLists.forEach((list) => {
            if (list.querySelector('li')) {
                list.classList.remove("groups--empty")
            } else {
                list.classList.add("groups--empty")
            }
        })
        if (this.unassignedSection) {
            if (!this.unassignedSection.querySelector('li')) {
                this.unassignedSection.classList.add("unassigned--empty")
            } else {
                this.unassignedSection.classList.remove("unassigned--empty")
            }
        }
        this.groupGenerationIcons.forEach((generationIcon) => {
            let list = generationIcon.closest('ul')
            let generation = 0
            while (list) {
                generation++
                list = list.parentNode.closest('ul')
            }
            generationIcon.innerText = generation
        })
    }

    /**
     * Save the parent connection to the server.
     * @param id
     * @param oldParentId
     * @param newParentId
     * @returns {Promise<void>}
     */
    async saveParentConnection(id, oldParentId, newParentId) {
        try {
            await this.fetch('onItemDrop', {
                self: id,
                new_parent: newParentId,
                previous_parent: oldParentId
            })
        } catch (e) {
            this.error = e.message
            console.error(e)
        }
    }
}

window.customElements.define("app-groups-tree", ChurchTile);
