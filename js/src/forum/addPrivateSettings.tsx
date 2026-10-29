import app from "flarum/forum/app";
import { extend } from "flarum/common/extend";
import Switch from "flarum/common/components/Switch";
import type SettingsPage from "flarum/forum/components/SettingsPage";

type PrivateSettingsPage = SettingsPage & { privateProfileLoading?: boolean };

export default function () {
  extend<PrivateSettingsPage, "settingsItems">(
    "flarum/forum/components/SettingsPage",
    "settingsItems",
    function (items) {
      const user = this.user;
      if (
        !user ||
        app.forum.attribute<string>("profileVisibilityMode") === "admin"
      )
        return;

      const audience =
        app.forum.attribute<string>("profilePrivacyAudience") === "members"
          ? "members"
          : "private";
      items.add(
        "profileVisibility",
        <div className="Form-group PrivateProfile-setting">
          <label>
            {app.translator.trans(
              "huseyinfiliz-private-profile.forum.user.settings.private_profile_heading",
            )}
          </label>
          <Switch
            state={user.attribute<boolean>("privateProfileEnabled")}
            disabled={this.privateProfileLoading}
            loading={this.privateProfileLoading}
            onchange={(value: boolean) => {
              this.privateProfileLoading = true;
              user
                .savePreferences({ privateProfileEnabled: value })
                .catch(() => {})
                .finally(() => {
                  this.privateProfileLoading = false;
                  m.redraw();
                });
            }}
          >
            {app.translator.trans(
              "huseyinfiliz-private-profile.forum.user.settings.private_profile_description",
            )}
          </Switch>
          <div className="helpText">
            {app.translator.trans(
              `huseyinfiliz-private-profile.forum.user.settings.private_profile_help_${audience}`,
            )}
          </div>
          <div className="helpText">
            {app.translator.trans(
              "huseyinfiliz-private-profile.forum.user.settings.private_profile_help_public",
            )}
          </div>
        </div>,
      );
    },
  );
}
