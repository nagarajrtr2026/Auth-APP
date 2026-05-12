/**
 * Login + register AJAX handlers (mirror of embedded script in ../login.html).
 * Pages use inline scripts per project spec; this file satisfies the assets/js layout
 * and can be wired via <script src="assets/js/login.js"> if you refactor.
 */
(function ($) {
  var apiLogin = 'php/login.php';
  var apiRegister = 'php/register.php';

  window.MTAuthLogin = {
    apiLogin: apiLogin,
    apiRegister: apiRegister,

    showLoading: function (show) {
      var el = $('#loadingOverlay');
      if (show) el.addClass('show').attr('aria-busy', 'true');
      else el.removeClass('show').attr('aria-busy', 'false');
    },

    showToast: function (message, type) {
      type = type || 'success';
      var $t = $('<div class="toast-custom ' + (type === 'error' ? 'error' : 'success') + '"></div>').text(message);
      $('#toastHost').append($t);
      setTimeout(function () {
        $t.fadeOut(320, function () { $(this).remove(); });
      }, 4200);
    },

    validateEmail: function (v) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    },

    submitLogin: function () {
      var tenant = $.trim($('#loginTenant').val()) || 'default';
      var email = $.trim($('#loginEmail').val());
      var pass = $('#loginPassword').val();
      $('#loginEmailErr').hide();
      $('#loginPassErr').hide();
      if (!window.MTAuthLogin.validateEmail(email)) {
        $('#loginEmailErr').show();
        return;
      }
      if (!pass) {
        $('#loginPassErr').show();
        return;
      }
      window.MTAuthLogin.showLoading(true);
      $.ajax({
        url: apiLogin,
        method: 'POST',
        contentType: 'application/json; charset=UTF-8',
        dataType: 'json',
        data: JSON.stringify({ tenant_id: tenant, email: email, password: pass }),
        success: function (res) {
          window.MTAuthLogin.showLoading(false);
          if (res && res.success && res.token) {
            localStorage.setItem('session_token', res.token);
            localStorage.setItem('session_user', JSON.stringify(res.user || {}));
            window.MTAuthLogin.showToast(res.message || 'Signed in successfully.', 'success');
            setTimeout(function () { window.location.href = 'profile.html'; }, 650);
          } else {
            window.MTAuthLogin.showToast((res && res.message) || 'Login failed.', 'error');
          }
        },
        error: function (xhr) {
          window.MTAuthLogin.showLoading(false);
          var msg = 'Unable to sign in.';
          try {
            var j = xhr.responseJSON || JSON.parse(xhr.responseText);
            if (j && j.message) msg = j.message;
          } catch (e) {}
          window.MTAuthLogin.showToast(msg, 'error');
        }
      });
    },

    submitRegister: function () {
      var tenant = $.trim($('#regTenant').val()) || 'default';
      var name = $.trim($('#regName').val());
      var email = $.trim($('#regEmail').val());
      var pass = $('#regPassword').val();
      $('#regNameErr, #regEmailErr, #regPassErr').hide();
      var ok = true;
      if (name.length < 2) {
        $('#regNameErr').show();
        ok = false;
      }
      if (!window.MTAuthLogin.validateEmail(email)) {
        $('#regEmailErr').show();
        ok = false;
      }
      if (!pass || pass.length < 8) {
        $('#regPassErr').show();
        ok = false;
      }
      if (!ok) return;
      window.MTAuthLogin.showLoading(true);
      $.ajax({
        url: apiRegister,
        method: 'POST',
        contentType: 'application/json; charset=UTF-8',
        dataType: 'json',
        data: JSON.stringify({
          tenant_id: tenant,
          name: name,
          email: email,
          password: pass
        }),
        success: function (res) {
          window.MTAuthLogin.showLoading(false);
          if (res && res.success) {
            window.MTAuthLogin.showToast(res.message || 'Registered. You can sign in now.', 'success');
            $('#panelsTrack').removeClass('show-register');
            $('#tabLogin').addClass('active');
            $('#tabRegister').removeClass('active');
            $('#loginEmail').val(email);
            $('#loginTenant').val(tenant);
          } else {
            window.MTAuthLogin.showToast((res && res.message) || 'Registration failed.', 'error');
          }
        },
        error: function (xhr) {
          window.MTAuthLogin.showLoading(false);
          var msg = 'Registration error.';
          try {
            var j = xhr.responseJSON || JSON.parse(xhr.responseText);
            if (j && j.message) msg = j.message;
          } catch (e) {}
          window.MTAuthLogin.showToast(msg, 'error');
        }
      });
    }
  };
})(jQuery);
