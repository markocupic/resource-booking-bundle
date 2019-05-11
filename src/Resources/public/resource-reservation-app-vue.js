/**
 * Chronometry Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package chronometry-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/chronometry-bundle
 */
var resourceReservationApp = new Vue({
    el: '#resourceReservationApp',
    data: {
        isReady: false,
        isOnline: '',
        requestToken: '',
        weekdays: [],
        timeSlots: [],
        rows: [],
        activeResource: {},
        activeResourceType: {},


        bookingModal: {
            action: '',
            showConfirmationMsg: false,
            alertSuccess: '',
            alertError: '',
            selectedTimeSlots: []
        },
        form: {},

    },
    created: function () {
        let self = this;
        self.requestToken = RESOURCE_RESERVATION.requestToken;

        window.setTimeout(function () {
            self.isReady = true;
        }, 800);

        //self.checkOnlineStatus();
        window.setInterval(function () {
            //self.checkOnlineStatus();
        }, 15000);

        self.getDataAll();

    },
    methods: {
        /**
         * Get all rows from server
         */
        getDataAll: function () {
            let self = this;
            let xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'REQUEST_TOKEN': self.requestToken,
                    'action': 'getDataAll'
                }
            });
            xhr.done(function (response) {
                self.weekdays = response.weekdays;
                self.rows = response.rows;
                self.timeSlots = response.timeSlots;
                self.activeResource = response.activeResource;
                self.activeResourceType = response.activeResourceType;

                console.log(response);

            });
            xhr.fail(function ($res, $bl) {
                console.log($res);
                console.log($bl);

                alert("XHR-Request fehlgeschlagen!!!");
            });
            xhr.always(function () {
                //
            });
        },

        /**
         * Open booking modal window
         * @param objActiveTimeSlot
         * @param action
         */
        openBookingModal: function (objActiveTimeSlot, action) {
            let self = this;
            self.bookingModal.selectedTimeSlots = [];
            self.bookingModal.action = action;
            self.bookingModal.showConfirmationMsg = false;
            self.bookingModal.activeTimeSlot = objActiveTimeSlot;
            self.bookingModal.alertSuccess = '';
            self.bookingModal.alertError = '';
            console.log(objActiveTimeSlot);

            self.bookingModal.selectedTimeSlots.push(objActiveTimeSlot.bookingCheckboxValue);


            $('#resourceBookingModal [name="bookingDescription"]').val('');
            $('#resourceBookingModal').modal('show');
        },
        /**
         * Send booking request
         */
        sendBookingRequest: function () {
            let self = this;
            let xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'action': 'sendBookingRequest',
                    'REQUEST_TOKEN': self.requestToken,
                    'resourceId': self.bookingModal.activeTimeSlot.resourceId,
                    'description': $('#resourceBookingModal [name="bookingDescription"]').val(),
                    'bookedTimeSlots': self.bookingModal.selectedTimeSlots,
                    'bookingRepeatStopWeekTstamp': $('#bookingRepeatStopWeekTstamp').val(),
                }
            });
            xhr.done(function (response) {
                if (response.status == 'success') {
                    self.bookingModal.alertSuccess = response.alertSuccess;
                    window.setTimeout(function(){$('#resourceBookingModal').modal('hide');},2000);
                } else {
                    self.bookingModal.alertError = response.alertError;
                }
                console.log(response);

            });
            xhr.fail(function () {
                self.isOnline = false;
            });
            xhr.always(function () {
                self.bookingModal.showConfirmationMsg = true;
                self.getDataAll();
            });
        },

        /**
         * Send booking request
         */
        sendCancelBookingRequest: function () {
            let self = this;
            let xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'action': 'sendCancelBookingRequest',
                    'REQUEST_TOKEN': self.requestToken,
                    'bookingId': self.bookingModal.activeTimeSlot.bookingId,
                }
            });
            xhr.done(function (response) {
                if (response.status == 'success') {
                    self.bookingModal.alertSuccess = response.alertSuccess;
                } else {
                    self.bookingModal.alertError = response.alertError;
                }
                console.log(response);

            });
            xhr.fail(function () {
                self.isOnline = false;
            });
            xhr.always(function () {
                self.bookingModal.showConfirmationMsg = true;
                self.getDataAll();
            });
        },

        /**
         * Save data to server
         * @param index
         */
        checkOnlineStatus: function () {
            let self = this;
            let xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'action': 'checkOnlineStatus',
                    'REQUEST_TOKEN': self.requestToken,
                }
            });
            xhr.done(function (response) {
                if (response.status === 'success') {
                    self.isOnline = true;
                } else {
                    self.isOnline = false;
                }
            });
            xhr.fail(function () {
                self.isOnline = false;
            });
            xhr.always(function () {
                //
            });

        },

        submitForm: function () {
            let self = this;
            document.getElementById('resourceReservationForm').submit();
        }


    }
});
