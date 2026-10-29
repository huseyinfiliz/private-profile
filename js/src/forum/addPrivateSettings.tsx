import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
// @TODO: SettingsPage is lazy loaded in 2.0, so this import is undefined until its chunk has loaded. Load it with `await import('flarum/forum/components/SettingsPage')` before using it. https://docs.flarum.org/2.x/extend/code-splitting
import SettingsPage from 'flarum/forum/components/SettingsPage';
import Switch from 'flarum/common/components/Switch';

export default function () {
  extend('flarum/forum/components/SettingsPage', 'settingsItems', function (this: SettingsPage, items) {
    items.add(
      'PrivateProfile',
      m('.Form-group', [
        m(
          'label.SettingsPage-PrivateProfile-label',
          app.translator.trans('huseyinfiliz-private-profile.forum.user.settings.private_profile_heading')
        ),
        m('.PrivateProfile-setting', [
          m(
            Switch,
            {
              state: this.user.preferences().PrivateProfile,
              onchange: (value) => {
                this.PrivateProfileLoading = true;
                this.user.savePreferences({ PrivateProfile: value }).then(() => {
                  this.PrivateProfileLoading = false;
                  m.redraw();
                });
              },
              loading: this.PrivateProfileLoading,
            },
            app.translator.trans('huseyinfiliz-private-profile.forum.user.settings.private_profile_description')
          ),
        ]),
        m('.helpText', app.translator.trans('huseyinfiliz-private-profile.forum.user.settings.private_profile_help')),
      ])
    );
  });
}
