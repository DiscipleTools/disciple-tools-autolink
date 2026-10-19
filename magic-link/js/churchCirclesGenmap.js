(function () {
    "use strict";
    let localizedObject = window.wpApiGenmapper;
    let chartDiv = jQuery("#chart"); // retrieves the chart div in the metrics page
    jQuery(document).ready(function () {
        show_template_overview();
    });

    function show_template_overview() {
        const windowHeight = document.documentElement.clientHeight;
        chartDiv.empty().html(`
      <div class="grid-x">
        <div class="cell">
          <hr style="max-width:100%;">
          <aside id="left-menu">
          </aside>
          <section id="intro" class="intro">
            <div id="intro-content"></div>
          </section>
          <section id="alert-message" class="alert-message">
          </section>
          <section id="edit-group" class="edit-group">
          </section>
          <section id="genmapper-graph" style="height:${
            document.documentElement.clientHeight - 250
        }px">
            <svg id="genmapper-graph-svg" width="100%"></svg>
          </section>
        </div>
      </div>
    `);

        window.genmapper = new window.genMapperClass();
        get_groups();

    }

    /*
     * Deliberately fetch() rather than jQuery.ajax().
     *
     * The theme binds a global jQuery ajaxComplete handler that calls
     * window.location.reload() whenever a response carries a 401, to send a
     * user whose session died back through the login. On a magic link there is
     * no login to go back through, so a 401 here reloaded a page that asked
     * again and 401'd again - an endless reload loop with an alert flashing in
     * and out of it. fetch() is invisible to that handler, so this screen
     * reports the error once and stays put.
     */
    function get_groups(group = null) {
        let loading_spinner = $(".loading-spinner");
        loading_spinner.addClass("active");

        // The magic link's parts travel with the request: this endpoint is
        // outside the magic link namespace, so they are how the server knows
        // whose churches to return. Without them a contact link, which has no
        // session, reads as nobody.
        const url = `${localizedObject.root}dt/v1/autolink/group-tree?${jQuery.param(
            {
                node: group,
                parts: localizedObject.magic_parts || {},
            }
        )}`;

        return fetch(url, {
            method: "GET",
            headers: {
                "Content-Type": "application/json; charset=utf-8",
                "X-WP-Nonce": localizedObject.nonce,
            },
        })
            .then(async (response) => {
                const body = await response.json().catch(() => null);

                if (!response.ok) {
                    throw body || {status: response.status};
                }

                return body;
            })
            .then((e) => {
                loading_spinner.removeClass("active");
                genmapper.importJSON(e, group === null);
                genmapper.origPosition(true);
            })
            .catch((err) => {
                loading_spinner.removeClass("active");
                displayError(err);
            });
    }

    /**
     * Show why the map could not be drawn.
     *
     * This used to call displayAlert() with no argument before deciding what to
     * say, which wrote the string "undefined" into the modal - and then only
     * replaced it in the one case where the response named a record.
     */
    function displayError(err) {
        const translations = localizedObject.translation || {};
        let message =
            (err && err.message) ||
            translations.error_loading_map ||
            "The map could not be loaded.";

        if (err && err.data && err.data.record && err.data.link) {
            message += ` <a target="_blank" href="${err.data.link}">${translations.open_record_label}</a>`;
        }

        window.genmapper.displayAlert(message);
    }

    chartDiv.on("rebase-node-requested", function (e, node) {
        get_groups(node.data.id);
    });

    /*
     * Writes go through the magic link's own endpoint, for the same reason
     * get_groups() uses fetch(): the theme's dt-posts API needs a login, so on a
     * contact link it answered 401 and the theme's ajaxComplete handler reloaded
     * the page. The server checks each write against the link's holder.
     */
    function post_to_app(body) {
        return fetch(window.app.rest_base + window.magic.rest_namespace, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": window.app.nonce,
            },
            body: JSON.stringify({
                ...body,
                parts: window.magic.parts,
            }),
        }).then(async (response) => {
            const result = await response.json().catch(() => null);

            if (!response.ok || !result) {
                throw result || {status: response.status};
            }

            return result;
        });
    }

    chartDiv.on("add-node-requested", function (e, parent) {
        let loading_spinner = $(".loading-spinner");
        loading_spinner.addClass("active");
        post_to_app({
            action: "genmap_create_node",
            parent_id: parent.data.id,
        })
            .then((newGroup) => {
                let newNodeData = {};
                newNodeData["id"] = newGroup["ID"];
                newNodeData["parentId"] = parent.data.id;
                newNodeData["name"] = newGroup["title"];
                // The box's label is drawn from line_1, which the server fills
                // with the name; left out, the new box has no label until reload.
                newNodeData["line_1"] = newGroup["title"];
                genmapper.createNode(newNodeData);
                loading_spinner.removeClass("active");
            })
            .catch((err) => {
                loading_spinner.removeClass("active");
                displayError(err);
            });
    });

    chartDiv.on("node-updated", function (e, nodeID, nodeFields, groupFields) {
        let loading_spinner = $(".loading-spinner");
        loading_spinner.addClass("active");
        window.lodash.forOwn(nodeFields, (value, key) => {
            if (key === "name") {
                groupFields["title"] = value;
            }
            if (key === "active") {
                groupFields["group_status"] = value ? "active" : "inactive";
            }
            if (key === "group_type") {
                groupFields["group_type"] = value;
            }
        });
        post_to_app({
            action: "genmap_update_node",
            group_id: nodeID,
            fields: groupFields,
        })
            .then(() => {
                loading_spinner.removeClass("active");
            })
            .catch((err) => {
                loading_spinner.removeClass("active");
                displayError(err);
            });
    });
})();
