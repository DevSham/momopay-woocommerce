var formPhoneField;
var formCountryField;
var phoneField;
var currencyField;

var sanitizedPhone = function (phone) {
    phone = phone.trim();
    if (phone.charAt(0) === '+'){
        phone = phone.substring(4, phone.length);
        return '0' + phone.trim();
    }
    return phone;
};

window.onload = function () {
    momopay_currencies = momopay_args.currencies;
    momopay_go_live = momopay_args.go_live;

    formPhoneField = document.getElementById('billing_phone');
    formCountryField = document.getElementById('billing_country');
    phoneField = document.getElementById('momopay_phone');
    currencyField = document.getElementById('momopay_currency');

    //initialize fields
    phoneField.value = formPhoneField.value;

    if (momopay_go_live){
        currencyField.value = momopay_currencies[formCountryField.value] || currencyField.value;
    }else {
        currencyField.value = 'EUR';
    }

    //update fields on form change
    formPhoneField.addEventListener('change', function (e) {
        phoneField.value = sanitizedPhone(this.value);
    });
};