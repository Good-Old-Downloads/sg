var regBtn = document.getElementById('regbtn');
var regCode = document.getElementById('regcode');
var loginForum = document.getElementById('login');
var loginErrors = document.getElementById('login-errors');

regBtn.addEventListener('click', showBtn);
loginForum.addEventListener('submit', function(evt){
    evt.preventDefault();
    var user = document.querySelector('[data-username]').value;
    var pass = document.querySelector('[data-password]').value;
    var regcode = document.querySelector('[data-regcode]').value;
    http.post({
        url:'/login/',
        data: {user: user, pass: pass, login: 1}
    }, function(data){
        var res = JSON.parse(data);
        if (res.SUCCESS) {
            window.location = '/';
        } else {
            alert(res.MSG);
        }
    });
});

function showBtn(evt){
    evt.preventDefault();
    regCode.classList.remove('hidden');
    regBtn.removeEventListener('click', showBtn);
    regBtn.addEventListener('click', register);
}

function register(evt){
    evt.preventDefault();
    var user = document.querySelector('[data-username]').value;
    var pass = document.querySelector('[data-password]').value;
    var regcode = document.querySelector('[data-regcode]').value;

    http.post({
        url: '/login/',
        data: {user: user, pass: pass, regcode: regcode, register: 1}
    }, function(data){
        var res = JSON.parse(data);
        if (res.SUCCESS) {
            alert('Success.');
            window.location = '/';
        } else {
            alert(res.MSG);
        }
    });
}