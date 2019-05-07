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

        modal: {

        },
        form: {

        },

    },
    created: function () {
        let self = this;

        window.setTimeout(function () {
            self.isReady = true;
        }, 2000);

        //self.checkOnlineStatus();
        window.setInterval(function () {
            //self.checkOnlineStatus();
        }, 15000);

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
                self.runners = response.data;
                self.stats = response.stats;
                self.categories = response.categories;
            });
            xhr.fail(function () {
                alert("XHR-Request fehlgeschlagen!!!");
            });
            xhr.always(function () {
                //
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

        submitForm: function(){
            let self = this;
            document.getElementById('resourceReservationForm').submit();
        }


    }
});
