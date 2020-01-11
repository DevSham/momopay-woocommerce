var formPhoneField;
var momoPhoneField;
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
    momoPhoneField = document.getElementById('momopay_phone');

    //initialize fields
    momoPhoneField.value = formPhoneField.value;

    //update fields on form change
    formPhoneField.addEventListener('change', function (e) {
        momoPhoneField.value = sanitizedPhone(this.value);
    });
};
