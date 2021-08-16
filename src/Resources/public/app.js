/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

"use strict";

class resourceBookingApp {
  constructor(vueElement, options) {
    new Vue({
      el: vueElement,
      data: {
        // Module options
        options: {
          requestToken: '',
          moduleKey: '',
          // The settings below are optional
          audio: {
            notifyOnNewBookingsAudio: 'bundles/markocupicresourcebooking/audio/booking-alert.mp3'
          },
          enableAudio: true,
          autocloseWindowsAfter: 2500,
          // Callback functions
          callbacks: {
            // Callback function to be executed before booking request is fired
            onBeforeBookingRequest: objFormData => {
              return true;
            },
            // Callback function to be executed after booking request was fired
            onAfterBookingRequest: () => {
            },
          },
        },
        // indicates if application is initialized, switches to true, when refreshData request was fired first time
        // and the request status is 200
        isReady: false,
        // Indicate the mode
        mode: 'main-window',
        // Indicates the last response code
        lastResponseCode: 200,
        // Contains data about available resource types, resources and weeks (week selector)
        filterBoard: null,
        // Indicates if the current user hass logged in as a frontend user
        userHasLoggedIn: false,
        // Contains the logged in user data
        loggedInUser: [],
        // Contains the weekdays
        weekdays: [],
        // Contains the time slots (first col in the booking table)
        timeSlots: [],
        // The cell data of a each row in the booking table
        rows: [],
        // Contains the id
        activeResourceTypeId: 'undefined',
        // Contains the data in an array: id, title, etc.
        activeResourceType: [],
        // Contains the id
        activeResourceId: 'undefined',
        // Contains the data in an array: id, title, etc.
        activeResource: [],
        // Beginn week weekday of current week 00:00 UTC
        activeWeekTstamp: 0,
        // Contains data about the active week: tstampStart, tstampEnd, dateStart, dateEnd, weekNumber, year
        activeWeek: [],
        bookingRepeatsSelection: [],
        bookingWindow: {
          action: null,
          activeTimeSlot: null,
          booking: null,
          response: {},
          deleteBookingsWithSameBookingUuid: false,
          selectedTimeSlots: [],
          showCancelBookingForm: false,
          showCancelBookingButton: false,
        },
        intervals: [],
        autoCloseBookingWindowTimeout: null,
        messages: null,
        // Indicates if user is idle
        isIdle: false,
        // Do not run refreshDataRequest() if there is a pending request
        isBusy: false,
      },

      created: function created() {

        // Detect unsupported browsers
        let ua = window.navigator.userAgent;
        let msie = ua.indexOf('MSIE ');
        if (msie > 0) {
          alert('This plugin is not compatible with your browser. Please use a current browser (like Opera, Firefox, Safari or Google Chrome), that is not out of date.')
        }

        // Override defaults
        this.options = {...this.options, ...options}

        // Show the loading spinner for 2s
        window.setTimeout(() => {
          this.refreshDataRequest();
        }, 2000);

        // Fetch data from server each 15s
        this.intervals.refreshDataRequest = window.setInterval(() => {
          if (!this.isIdle && !this.isBusy) {
            this.refreshDataRequest();
          }
        }, 15000);

        // Initialize idle detector
        // Idle after 5 min (300000 ms)
        let idleAfter = 300000;
        window.setTimeout(() => {
          this.initializeIdleDetector(document, idleAfter);
        }, 10000);

        document.addEventListener('keyup', evt => {
          if (evt.keyCode === 27 && this.mode === 'booking-window') {
            this.hideBookingWindow();
          }
        });
      },

      // Watchers
      watch: {
        // Watcher
        isReady: function isReady(newVal, oldVal) {
          //
        },
        activeResourceTypeId: function activeResourceTypeId(newVal, oldVal) {
          this.applyFilterRequest(newVal, this.activeResourceId, this.activeWeekTstamp);
        },
        activeResourceId: function activeResourceId(newVal, oldVal) {
          this.applyFilterRequest(this.activeResourceTypeId, newVal, this.activeWeekTstamp);
        },
        activeWeekTstamp: function activeWeekTstamp(newVal, oldVal) {
          this.applyFilterRequest(this.activeResourceTypeId, this.activeResourceId, newVal);
        },
        rows: async function (newVal, oldVal) {

          if (newVal.length === 0 || oldVal.length === 0) {
            return;
          }

          let newBooking = false;

          Object.keys(newVal).forEach(rowIndex => {
            Object.keys(newVal[rowIndex]['cellData']).forEach(colIndex => {
              if (parseInt(newVal[rowIndex]['cellData'][colIndex]['bookingCount']) > parseInt(oldVal[rowIndex]['cellData'][colIndex]['bookingCount'])) {
                if (newVal[rowIndex]['cellData'][colIndex]['beginnWeekTimestampSelectedWeek'] === oldVal[rowIndex]['cellData'][colIndex]['beginnWeekTimestampSelectedWeek']) {
                  if (newVal[rowIndex]['cellData'][colIndex]['pid'] === oldVal[rowIndex]['cellData'][colIndex]['pid']) {
                    newBooking = true;
                  }
                }
              }
            });
          });

          if (newBooking === true) {
            if (this.options.enableAudio) {
              this.playAudio(this.options.audio.notifyOnNewBookingsAudio);
            }
          }
        }
      },

      methods: {

        /**
         * Fetch all the data from the server and refresh the booking table
         */
        refreshDataRequest: function refreshDataRequest() {

          let action = 'refreshDataRequest';
          this.isBusy = true;

          let data = new FormData();
          data.append('REQUEST_TOKEN', this.options.requestToken);
          data.append('action', action);
          data.append('moduleKey', this.options.moduleKey);

          // Fetch
          fetch(window.location.href, {
            method: "POST",
            body: data,
            headers: {
              'x-requested-with': 'XMLHttpRequest'
            },
          })
            .then(async response => {
              this.lastResponseCode = response.status;
              let data = await response.json();
              if (!response.ok) {
                let error = response.statusText;
                return Promise.reject(error);
              }
              this.checkResponse(response);
              return data;
            })
            .then(response => {
              if (response.status === 'success') {
                for (let key in response['data']) {
                  this[key] = response['data'][key];
                }
              }
              return response;
            })
            .then(response => {
              this.isReady = true;
              this.isBusy = false;
            })
            .catch(error => {
              this.isReady = false;
              this.isBusy = false;
              console.error("There was en error: " + error);
            });
        },

        /**
         * Apply the filter changes
         */
        applyFilterRequest: function applyFilterRequest(activeResourceTypeId, activeResourceId, activeWeekTstamp) {

          let action = 'applyFilterRequest';
          this.isBusy = true;

          let data = new FormData();
          data.append('REQUEST_TOKEN', this.options.requestToken);
          data.append('action', action);
          data.append('resType', activeResourceTypeId);
          data.append('res', activeResourceId);
          data.append('date', activeWeekTstamp);
          data.append('moduleKey', this.options.moduleKey);

          fetch(window.location.href, {
            method: "POST",
            body: data,
            headers: {
              'x-requested-with': 'XMLHttpRequest'
            },
          })
            .then(async response => {
              this.lastResponseCode = response.status;
              let data = await response.json();
              if (!response.ok) {
                let error = response.statusText;
                return Promise.reject(error);
              }
              this.checkResponse(response);
              return data;
            })
            .then(response => {
              if (response.status === 'success') {
                Object.keys(response.data).forEach(key => {
                  this[key] = response.data[key];
                });
              }
              return response;
            })
            .then(response => {
              this.isBusy = false;
            })
            .catch(response => {
              this.isBusy = false;
              console.error("There was en error: " + error);
            });
        },

        /**
         * Send booking request
         */
        bookingRequest: function bookingRequest() {

          let action = 'bookingRequest';

          let form = this.$el.querySelector('.rbb-js-booking-form');
          if (!form) {
            console.error('Form not found');
          }

          let data = new FormData(form);
          data.append('REQUEST_TOKEN', this.options.requestToken);
          data.append('action', action);
          data.append('resourceId', this.bookingWindow.activeTimeSlot.pid);
          data.append('moduleKey', this.options.moduleKey);

          Object.keys(this.bookingWindow.selectedTimeSlots).forEach(key => {
            data.append('bookingDateSelection[]', this.bookingWindow.selectedTimeSlots[key]);
          });

          // Call onBeforeBookingRequest callback
          if (this.options.callbacks.onBeforeBookingRequest.call(this, data) === true) {
            fetch(window.location.href,
              {
                method: "POST",
                body: data,
                headers: {
                  'x-requested-with': 'XMLHttpRequest'
                },
              })
              .then(async response => {
                this.lastResponseCode = response.status;
                let data = await response.json();
                if (!response.ok) {
                  let error = response.statusText;
                  return Promise.reject(error);
                }
                this.checkResponse(response);
                return data;
              })
              .then(response => {
                this.bookingWindow.response = response.data;

                if (response.status === 'success') {
                  this.autoCloseBookingWindowTimeout = window.setTimeout(() => {
                    this.mode = 'main-window';
                  }, this.options.autocloseWindowsAfter);
                }

                // Always
                this.refreshDataRequest();
              })
              .then(response => {
                // Call onAfterBookingRequest callback
                this.options.callbacks.onAfterBookingRequest.call(this, data);
              })
              .catch(response => {
                this.isReady = false;
                console.error("There was en error: " + error);
                // Always
                this.refreshDataRequest();
              });
          }
        },

        /**
         * Send resource availability request
         */
        bookingFormValidationRequest: function bookingFormValidationRequest() {
          let action = 'bookingFormValidationRequest';

          let data = new FormData();
          data.append('REQUEST_TOKEN', this.options.requestToken);
          data.append('action', action);
          data.append('resourceId', this.bookingWindow.activeTimeSlot.pid);
          data.append('bookingRepeatStopWeekTstamp', this.$el.querySelector('.rbb-js-booking-repeat-stop-week-tstamp').value);
          data.append('moduleKey', this.options.moduleKey);
          data.append('itemsBooked', this.$el.querySelector('[name="itemsBooked"]') ? this.$el.querySelector('[name="itemsBooked"]').value : '1');

          Object.keys(this.bookingWindow.selectedTimeSlots).forEach(key => {
            data.append('bookingDateSelection[]', this.bookingWindow.selectedTimeSlots[key]);
          });

          fetch(window.location.href,
            {
              method: "POST",
              body: data,
              headers: {
                'x-requested-with': 'XMLHttpRequest'
              },
            })
            .then(async response => {
              this.lastResponseCode = response.status;
              let data = await response.json();
              if (!response.ok) {
                let error = response.statusText;
                return Promise.reject(error);
              }
              this.checkResponse(response);
              return data;
            })
            .then(response => {
              if (response.status) {
                this.bookingWindow.response = response.data;
              }

              if (response.status === 'success') {
                this.isReady = true;
              }
            })
            .catch(response => {
              this.isReady = false;
              console.error("There was en error: " + error);
            });
        },

        /**
         * Send cancel booking request
         */
        cancelBookingRequest: function cancelBookingRequest() {
          this.isBusy = true;
          this.bookingWindow.showCancelBookingForm = false;
          this.bookingWindow.showCancelBookingButton = false;

          let action = 'cancelBookingRequest';
          let data = new FormData();
          data.append('REQUEST_TOKEN', this.options.requestToken);
          data.append('action', action);
          data.append('id', this.bookingWindow.booking.id);
          data.append('deleteBookingsWithSameBookingUuid', this.bookingWindow.deleteBookingsWithSameBookingUuid);
          data.append('moduleKey', this.options.moduleKey);

          fetch(window.location.href, {
            method: "POST",
            body: data,
            headers: {
              'x-requested-with': 'XMLHttpRequest'
            },
          })
            .then(async response => {
              this.lastResponseCode = response.status;
              let data = await response.json();
              if (!response.ok) {
                let error = response.statusText;
                return Promise.reject(error);
              }
              this.checkResponse(response);
              return data;
            })
            .then(response => {
              if (response.status === 'success') {
                this.bookingWindow.response = response.data;
                this.autoCloseBookingWindowTimeout = window.setTimeout(() => {
                  this.mode = 'main-window';
                }, this.options.autocloseWindowsAfter);
              } else {
                this.bookingWindow.response = response.data;
              }

              // Always
              self.isBusy = false;
              this.bookingWindow.deleteBookingsWithSameBookingUuid = false;
              this.refreshDataRequest();
            })
            .catch(response => {
              this.isReady = false;
              this.isBusy = false;
              console.error("There was en error: " + error);

              // Always
              this.refreshDataRequest();
              this.bookingWindow.deleteBookingsWithSameBookingUuid = false;
            });
        },

        /**
         * Jump to next/previous week
         * @param tstamp
         * @param evt
         */
        jumpWeekRequest: function jumpWeekRequest(tstamp, evt) {

          evt.preventDefault();
          evt.stopPropagation();

          if (this.isBusy) {
            return false;
          }

          // Prevent bubbling invalid requests
          if (tstamp === this.activeWeekTstamp || tstamp < this.filterBoard.tstampFirstPermittedWeek || tstamp > this.filterBoard.tstampLastPermittedWeek) {
            return false;
          }

          // Vue watcher will trigger this.applyFilterRequest()
          this.activeWeekTstamp = tstamp;
        },

        /**
         * @param slot
         * @param action
         * @param booking
         */
        openBookingWindow: function openBookingWindow(slot, action, booking = null) {
          this.mode = 'booking-window';

          // Reset
          this.bookingWindow = {
            'action': action,
            'activeTimeSlot': slot,
            'booking': booking,
            'response': {},
            'deleteBookingsWithSameBookingUuid': false,
            'selectedTimeSlots': [],
          }

          this.bookingWindow.selectedTimeSlots.push(slot.bookingCheckboxValue);

          if (action === 'showBookingForm') {
            window.setTimeout(() => {
              this.bookingFormValidationRequest();
            }, 100);
          } else if (action === 'showCancelBookingForm') {
            this.bookingWindow.showCancelBookingButton = true;
            this.bookingWindow.showCancelBookingForm = true;
          }

          // Wrap this code, otherwise querySelector will not find dom elements
          window.setTimeout(() => {
            let inputBookingDescription = this.$el.querySelector('.rbb-js-booking-form input[name="bookingDescription"]');
            if (inputBookingDescription !== null) {
              inputBookingDescription.setAttribute('value', '');
            }

            let weekRepeatOptions = this.$el.querySelectorAll('.rbb-js-booking-form .rbb-js-booking-repeat-stop-week-tstamp option');
            if (weekRepeatOptions.length > 0) {
              weekRepeatOptions.forEach(elOption => elOption.removeAttribute('selected'));
            }
          }, 20);
        },

        /**
         * Hide booking window
         */
        hideBookingWindow: function hideBookingWindow() {
          clearTimeout(this.autoCloseBookingWindowTimeout);
          this.mode = 'main-window';
        },


        /**
         * Check json response
         * @param res
         */
        checkResponse: function checkResponse(res) {
          this.lastResponseCode = res.status;
          if (res.status != 200) {
            this.isReady = false;
          } else {
            this.isReady = true;
          }
        },

        /**
         * Initialize idle detector
         */
        initializeIdleDetectorOld: function initializeIdleDetectorOld(idleAfter) {

          $(document).idle({
            onIdle: () => {
              this.isIdle = true;
            },
            onActive: () => {
              this.isIdle = false;
              this.refreshDataRequest();
            },
            idle: idleAfter,
          });
        },

        /**
         * Initialize idle detector
         * @param el
         * @param idleTimeout
         */
        initializeIdleDetector: function initializeIdleDetector(el, idleTimeout) {

          let idleSecondsCounter = idleTimeout;
          let listenerType = ['keydown', 'mousemove', 'mousedown', 'touchstart'];

          listenerType.forEach(type => {

            el.addEventListener(type, () => {
              if (this.isIdle) {
                // On active again
                this.isIdle = false;
                this.refreshDataRequest();
              }

              idleSecondsCounter = idleTimeout;
            }, false);
          });

          window.setInterval(() => {
            if (this.isIdle) {
              return;
            }

            idleSecondsCounter -= 1000;
            if (idleSecondsCounter <= 0) {
              // On idle
              this.isIdle = true;
            }
          }, 1000);
        },

        /**
         * Play audio file
         * @param src
         */
        playAudio: function playAudio(src) {
          (new Audio(src)).play();
        }
      }
    });
  }
}



