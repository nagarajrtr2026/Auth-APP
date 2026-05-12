/**
 * Standalone register flow (mirror of embedded script in ../register.html).
 */
(function ($) {
  window.MTAuthRegister = {
    api: 'php/register.php',

    toast: function (msg, ok) {
      var cls = ok ? 'ok' : 'bad';
      var $t = $('<div class="toast-custom ' + cls + '"></div>').text(msg);
      $('#toastHost').append($t);
      setTimeout(function () { $t.fadeOut(280, function () { $(this).remove(); }); }, 4000);
    },

    loading: function (on) {
      $('#loadingOverlay').toggleClass('show', !!on);
    },

    validateEmail: function (v) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    },

    submit: function () {
      var tenant = $.trim($('#tenant').val()) || 'default';
      var name = $.trim($('#name').val());
      var email = $.trim($('#email').val());
      var password = $('#password').val();
      $('#nameErr, #emailErr, #passErr').hide();
      var ok = true;
      if (name.length < 2) {
        $('#nameErr').show();
        ok = false;
      }
      if (!window.MTAuthRegister.validateEmail(email)) {
        $('#emailErr').show();
        ok = false;
      }
      if (!password || password.length < 8) {
        $('#passErr').show();
        ok = false;
      }
      if (!ok) {
        $('.card-3d').addClass('shake');
        setTimeout(function () { $('.card-3d').removeClass('shake'); }, 500);
        return;
      }
      window.MTAuthRegister.loading(true);
      $.ajax({
        url: window.MTAuthRegister.api,
        method: 'POST',
        contentType: 'application/json; charset=UTF-8',
        dataType: 'json',
        data: JSON.stringify({
          tenant_id: tenant,
          name: name,
          email: email,
          password: password
        }),
        success: function (res) {
          window.MTAuthRegister.loading(false);
          if (res && res.success) {
            window.MTAuthRegister.toast(res.message || 'Success. Redirecting…', true);
            setTimeout(function () { window.location.href = 'login.html'; }, 900);
          } else {
            window.MTAuthRegister.toast((res && res.message) || 'Registration failed.', false);
          }
        },
        error: function (xhr) {
          window.MTAuthRegister.loading(false);
          var msg = 'Could not register.';
          try {
            var j = xhr.responseJSON || JSON.parse(xhr.responseText);
            if (j && j.message) msg = j.message;
          } catch (err) {}
          window.MTAuthRegister.toast(msg, false);
        }
      });
    }
  };
})(jQuery);
