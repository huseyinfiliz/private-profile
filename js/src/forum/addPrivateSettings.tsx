import app from "flarum/forum/app";
import { extend } from "flarum/common/extend";
import type SettingsPage from "flarum/forum/components/SettingsPage";
import Switch from "flarum/common/components/Switch";

type PrivateSettingsPage = SettingsPage & { privateProfileLoading?: boolean };

export default function () {
  extend<PrivateSettingsPage, "settingsItems">(
    "flarum/forum/components/SettingsPage",
    "settingsItems",
    function (items) {
      items.add(
        "PrivateProfile",
        m(".Form-group", [
          m(
            "label.SettingsPage-PrivateProfile-label",
            app.translator.trans(
              "huseyinfiliz-private-profile.forum.user.settings.private_profile_heading",
            ),
          ),
          m(".PrivateProfile-setting", [
            m(
              Switch,
              {
                state: this.user!.preferences()?.PrivateProfile ?? false,
                onchange: (value: boolean) => {
                  this.privateProfileLoading = true;
                  this.user!.savePreferences({ PrivateProfile: value })
                    .then(() => {
                      this.user!.pushAttributes({ privateProfile: value });
                    })
                    .catch(() => {})
                    .finally(() => {
                      this.privateProfileLoading = false;
                      m.redraw();
                    });
                },
                loading: this.privateProfileLoading,
              },
              app.translator.trans(
                "huseyinfiliz-private-profile.forum.user.settings.private_profile_description",
              ),
            ),
          ]),
          m(
            ".helpText",
            app.translator.trans(
              "huseyinfiliz-private-profile.forum.user.settings.private_profile_help",
            ),
          ),
        ]),
      );
    },
  );
}
