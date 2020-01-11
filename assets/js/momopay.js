var formPhoneField;
var formCountryField;
var phoneField;
var sanitizedPhone = function (phone) {
    phone = phone.trim();
    if (phone.charAt(0) === '+'){
        phone = phone.substring(4, phone.length);
        return '0' + phone.trim();
    }
    return phone;
};

window.onload = function () {
    momopay_go_live = momopay_args.go_live;

    formPhoneField = document.getElementById('billing_phone');
    formCountryField = document.getElementById('billing_country');
    phoneField = document.getElementById('momopay_phone');

    //initialize fields
    phoneField.value = formPhoneField.value;
};

//update fields on form change

jQuery('#billing_phone').on('change', function (e) {
    phoneField.value = sanitizedPhone(this.value);
});
