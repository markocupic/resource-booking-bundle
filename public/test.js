"use strict";

if (typeof VueEventList !== 'function') {

    /*
     * This file is part of SAC Event Tool Bundle.
     *
     * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
     * @license GPL-3.0-or-later
     * For the full copyright and license information,
     * please view the LICENSE file that was distributed with this source code.
     * @link https://github.com/markocupic/sac-event-tool-bundle
     */
    window.VueEventList = class {
        constructor(elId, opt) {
            // Defaults
            const defaults = {
                'modId': null,
                'csrfToken': null,
                'apiParams': {
                    'organizers': [],
                    'eventType': ["tour", "generalEvent", "lastMinuteTour", "course"],
                    'suitableForBeginners': '',
                    'publicTransportEvent': '',
                    'favoredEvent': '',
                    'tourType': '',
                    'courseType': '',
                    'courseId': '',
                    'year': '',
                    'dateStart': '',
                    'textSearch': '',
                    'eventId': '',
                    'username': '',
                    // Let empty for all published
                    'calendarIds': [],
                    'limit': '50',
                    'offset': '0',
                },
                'fields': [],
            };

            // Merge options and defaults
            const params = {...defaults, ...opt}

            if (null === params.csrfToken) {
                console.error('No CSRF token has been set.');
            }

            const {createApp} = Vue

            // Instantiate vue.js application
            const app = createApp({
                data() {
                    return {
                        // The element CSS ID selector: e.g. #myList
                        elId: elId,
                        // The module id (used by the take param)
                        modId: params.modId,
                        // Api params
                        apiParams: params.apiParams,
                        // Fields array
                        fields: (params.fields && Array.isArray(params.fields)) ? params.fields : null,
                        // Result row
                        rows: [],
                        // Loaded events (ids)
                        arrEventIds: [],
                        // is busy boolean
                        blnIsBusy: false,
                        // total found items
                        itemsTotal: 0,
                        // already loaded items
                        loadedItems: 0,
                        // all events loaded bool
                        blnAllEventsLoaded: false,
                    };
                },
                mounted() {
                    const self = this;
                    self.prepareRequest();
                },
                methods: {
                    // Prepare ajax request
                    prepareRequest: function prepareRequest() {
                        const self = this;
                        if (self.blnIsBusy === false) {
                            self.blnIsBusy = true;
                            self.fetchItems();
                        }
                    },

                    favorEvent: function favorEvent(index) {
                        const affectedRow = this.rows[index];
                        const eventId = affectedRow.id;

                        const formData = new FormData();
                        formData.append('eventId', eventId);
                        formData.append('REQUEST_TOKEN', params.csrfToken);

                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest',
                            },
                        }).then(response => {
                            if (response.ok) {
                                return response.json();
                            }
                        }).then(json => {
                            if (json['status'] === 'success') {
                                this.rows[index]['isFavoredEvent'] = json['isFavoredEvent'];
                            }
                        }).catch(error => {
                            console.error(error.message);
                        })
                    },

                    getTake: function getTake() {
                        const self = this;
                        const urlString = window.location.href;
                        const url = new URL(urlString);
                        const take = url.searchParams.get('take_e' + self.modId);

                        return null === take ? null : parseInt(take);
                    },

                    // Load items from server or indexed database
                    fetchItems: async function fetchItems() {
                        const self = this;

                        // Initialize indexedDB
                        const db = new Dexie('SacEventToolEventListing');
                        db.version(1).stores({
                            eventStore: '++id, path, expires'
                        });

                        // Delete expired data
                        await db.eventStore.where("expires")
                        .below(Math.round(+new Date() / 1000))
                        .delete()
                        ;

                        const eventStoreData = await db.eventStore
                            .where("path")
                            .equals(btoa(window.location.href + '&modId=' + self.modId))
                            .first()
                        ;

                        // Search data in forward backend (indexed database) cache
                        if (eventStoreData && eventStoreData.path && eventStoreData.vueDataSerialized) {

                            // Delete data from indexed database
                            await db.eventStore.delete(eventStoreData.id);

                            const vueData = JSON.parse(eventStoreData.vueDataSerialized);

                            // Return if storage data is outdated
                            if (vueData.expiry < Date.now()) {
                                return;
                            }

                            console.log('Loaded events from the indexed database.');

                            self.rows = vueData.rows;
                            self.arrEventIds = vueData.arrEventIds;
                            self.itemsTotal = vueData.itemsTotal;
                            self.loadedItems = vueData.loadedItems;
                            self.blnAllEventsLoaded = vueData.blnAllEventsLoaded;
                            self.blnIsBusy = false;

                            await self.$nextTick();

                            // Create the on insert event
                            const onInsertEvent = new CustomEvent("sac_evt.event_list.insert", {
                                detail: {
                                    'vueInstance': self,
                                    'json': null,
                                },
                            });

                            // Dispatch the on insert event
                            document.querySelector(self.elId).dispatchEvent(onInsertEvent);

                            // Scroll to last mouse click position
                            (() => {
                                const scrollToSelector = eventStoreData.selector;
                                const elScrollTo = document.querySelector('[data-selector="' + scrollToSelector + '"]');

                                if (elScrollTo) {
                                    elScrollTo.scrollIntoView({behavior: "instant"});
                                }
                            })();

                            const url = new URL(window.location.href);
                            const urlParams = new URLSearchParams(url.search);
                            const href = window.location.protocol + '//' + window.location.hostname + window.location.pathname;

                            // Remove the "itemId" parameter when the user returns from the detail view
                            if (urlParams.has('itemId')) {
                                urlParams.delete('itemId');
                            }

                            // Current URL: https://my-website.ch/demo_a.html?take_e234=200
                            const nextURL = href + (urlParams.toString() ? '?' + urlParams.toString() : '');
                            const nextTitle = document.title; // keep the same title

                            // This will create a new entry in the browser's history, without reloading
                            window.history.replaceState({}, nextTitle, nextURL);

                            return;
                        }

                        if (self.blnAllEventsLoaded === true) {
                            return;
                        }

                        const formData = new FormData();

                        // Add api parameters to the Form Data object
                        for (const [key, value] of Object.entries(self.apiParams)) {
                            if (key === 'offset') {
                                formData.append('offset', parseInt(value) + self.loadedItems);
                            } else if (Array.isArray(value)) {// Handle arrays correctly
                                for (let i = 0; i < value.length; ++i) {
                                    formData.append(key + '[]', value[i]);
                                }
                            } else {
                                formData.append(key, value);
                            }
                        }

                        // Set limit on page load/refresh
                        if (self.loadedItems === 0 && self.getTake() > 0) {
                            if (formData.has('limit')) {
                                formData.set('limit', self.getTake());
                            } else {
                                formData.append('limit', self.getTake());
                            }
                        }

                        // Handle fields correctly
                        for (const prop of self.fields) {
                            formData.append('fields[]', prop);
                        }

                        const urlParams = new URLSearchParams(Array.from(formData)).toString();
                        const url = window.location.protocol + '//' + window.location.hostname + '/eventApi/events?' + urlParams;

                        // Dispatch the fetchsacevents event
                        const event = new CustomEvent('sac_evt.event_list.pre_fetch', {
                            'detail': {
                                'url': url,
                                'modId': self.modId,
                                'instance': self,
                            }
                        });

                        document.dispatchEvent(event);

                        // Fetch
                        fetch(url, {
                                headers: {
                                    'x-requested-with': 'XMLHttpRequest'
                                },
                            }
                        ).then(function (res) {
                            return res.json();
                        }).then(function (json) {

                            self.blnIsBusy = false;

                            let i = 0;
                            self.itemsTotal = parseInt(json['meta']['itemsTotal']);
                            for (const row of json['data']) {
                                i++;
                                row.selector = self.modId + '-' + row.id;
                                self.rows.push(row);
                                self.loadedItems++;
                            }

                            // Store all ids of loaded events in self.arrEventIds
                            for (const id of json['meta']['arrEventIds']) {
                                self.arrEventIds.push(id);
                            }

                            if (i === 0 || parseInt(json['meta']['itemsTotal']) === self.loadedItems) {
                                self.blnAllEventsLoaded = true
                            }

                            if (self.blnAllEventsLoaded === true) {
                                //console.log('Finished downloading process. ' + self.loadedItems + ' events loaded.');
                            }
                            return json;
                        }).then(function (json) {
                            let take = self.getTake();

                            const url = new URL(window.location.href);
                            const urlParams = new URLSearchParams(url.search);
                            const href = window.location.protocol + '//' + window.location.hostname + window.location.pathname;

                            if (self.loadedItems > self.apiParams['limit']) {
                                take = self.loadedItems;
                                if (!urlParams.has('take_e' + self.modId)) {
                                    urlParams.append('take_e' + self.modId, take);
                                } else {
                                    urlParams.set('take_e' + self.modId, take);
                                }

                                // Current URL: https://my-website.ch/demo_a.html?take_324=200
                                const nextURL = href + '?' + urlParams.toString();
                                const nextTitle = document.title; // keep the original title

                                // This will create a new entry in the browser's history, without reloading
                                window.history.replaceState({}, nextTitle, nextURL);
                            }

                            return json;

                        }).then(function (json) {
                            const onInsertEvent = new CustomEvent("sac_evt.event_list.insert", {
                                detail: {
                                    vueInstance: self,
                                    'json': json,
                                },
                            });

                            document.querySelector(self.elId).dispatchEvent(onInsertEvent);
                            return json;
                        });

                    },
                }
            });
            app.config.compilerOptions.delimiters = ['[[ ', ' ]]'];
            app.mount(elId);
        }
    }
}