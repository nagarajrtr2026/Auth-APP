/**
 * Profile load/save (mirror of embedded script in ../profile.html).
 */
(function ($) {
  var apiProfile = 'php/profile.php';

  window.MTAuthProfile = {
    apiProfile: apiProfile,

    toast: function (msg, ok) {
      var c = ok ? 'good' : 'bad';
      var $t = $('<div class="toast-custom ' + c + '"></div>').text(msg);
      $('#toastHost').append($t);
      setTimeout(function () { $t.fadeOut(280, function () { $(this).remove(); }); }, 4200);
    },

    loading: function (on) {
      $('#loadingOverlay').toggleClass('show', !!on);
    },

    getToken: function () {
      return localStorage.getItem('session_token');
    },

    redirectLogin: function () {
      localStorage.removeItem('session_token');
      localStorage.removeItem('session_user');
      window.location.href = 'login.html';
    },

    fillForms: function (data) {
      if (!data || !data.user) return;
      var u = data.user;
      var p = data.profile || {};
      $('#pfName').val(u.name || '');
      $('#pfEmail').val(u.email || '');
      $('#pfTenant').val(u.tenant_id || '');
      $('#pfId').val(u.id != null ? String(u.id) : '');
      $('#pfAge').val(p.age != null && p.age !== '' ? p.age : '');
      $('#pfDob').val(p.dob || '');
      $('#pfContact').val(p.contact || '');
      $('#pfBio').val(p.bio || '');
    },

    load: function () {
      var token = window.MTAuthProfile.getToken();
      if (!token) {
        window.MTAuthProfile.toast('Please sign in first.', false);
        setTimeout(window.MTAuthProfile.redirectLogin, 900);
        return;
      }
      window.MTAuthProfile.loading(true);
      $.ajax({
        url: apiProfile,
        method: 'GET',
        dataType: 'json',
        headers: { 'X-Session-Token': token },
        success: function (res) {
          window.MTAuthProfile.loading(false);
          if (res && res.success) {
            window.MTAuthProfile.fillForms(res);
            localStorage.setItem('session_user', JSON.stringify(res.user || {}));
          } else {
            window.MTAuthProfile.toast((res && res.message) || 'Could not load profile.', false);
            setTimeout(window.MTAuthProfile.redirectLogin, 1200);
          }
        },
        error: function (xhr) {
          window.MTAuthProfile.loading(false);
          var msg = 'Session invalid or expired.';
          try {
            var j = xhr.responseJSON || JSON.parse(xhr.responseText);
            if (j && j.message) msg = j.message;
          } catch (e) {}
          window.MTAuthProfile.toast(msg, false);
          setTimeout(window.MTAuthProfile.redirectLogin, 1200);
        }
      });
    },

    save: function () {
      var token = window.MTAuthProfile.getToken();
      if (!token) {
        window.MTAuthProfile.redirectLogin();
        return;
      }
      var name = $.trim($('#pfName').val());
      var email = $.trim($('#pfEmail').val());
      var ageVal = $('#pfAge').val();
      var dob = $('#pfDob').val();
      var contact = $.trim($('#pfContact').val());
      var bio = $.trim($('#pfBio').val());
      if (!name || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        window.MTAuthProfile.toast('Please fix name and email.', false);
        return;
      }
      var agePayload = '';
      if (ageVal !== '') {
        var parsedAge = parseInt(ageVal, 10);
        if (isNaN(parsedAge) || parsedAge < 0) {
          window.MTAuthProfile.toast('Age must be a valid number.', false);
          return;
        }
        agePayload = parsedAge;
      }
      window.MTAuthProfile.loading(true);
      $.ajax({
        url: apiProfile,
        method: 'POST',
        contentType: 'application/json; charset=UTF-8',
        dataType: 'json',
        data: JSON.stringify({
          token: token,
          name: name,
          email: email,
          age: agePayload,
          dob: dob,
          contact: contact,
          bio: bio
        }),
        success: function (res) {
          window.MTAuthProfile.loading(false);
          if (res && res.success) {
            window.MTAuthProfile.fillForms(res);
            window.MTAuthProfile.toast(res.message || 'Saved.', true);
          } else {
            window.MTAuthProfile.toast((res && res.message) || 'Update failed.', false);
          }
        },
        error: function (xhr) {
          window.MTAuthProfile.loading(false);
          var msg = 'Could not save.';
          try {
            var j = xhr.responseJSON || JSON.parse(xhr.responseText);
            if (j && j.message) msg = j.message;
          } catch (e) {}
          window.MTAuthProfile.toast(msg, false);
        }
      });
    }
  };
})(jQuery);
