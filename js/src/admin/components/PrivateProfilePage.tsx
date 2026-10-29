import app from "flarum/admin/app";
import ExtensionPage from "flarum/admin/components/ExtensionPage";
import Button from "flarum/common/components/Button";
import Form from "flarum/common/components/Form";
import Icon from "flarum/common/components/Icon";
import extractText from "flarum/common/utils/extractText";
import TabDiscovery from "./TabDiscovery";
import type { ProfileTab } from "../../forum/profileTabs";

type PageTab = "supported" | "pageOnly" | "settings";
const PAGE_TABS: PageTab[] = ["supported", "pageOnly", "settings"];
const VISIBILITY_SETTING = "huseyinfiliz-private-profile.default_visibility";
const MODE_SETTING = "huseyinfiliz-private-profile.visibility_mode";
const AUDIENCE_SETTING = "huseyinfiliz-private-profile.private_visibility";

export default class PrivateProfilePage extends ExtensionPage {
  private activeTab: PageTab = "supported";

  private label(tab: ProfileTab) {
    return tab.id.startsWith("auto_")
      ? tab.label || tab.path
      : app.translator.trans(
          `huseyinfiliz-private-profile.admin.tabs.${tab.id}`,
        );
  }

  private changeTab(tab: PageTab, focus = false) {
    this.activeTab = tab;
    if (focus)
      this.element
        .querySelector<HTMLButtonElement>(`#private-profile-${tab}-tab`)
        ?.focus();
  }

  private onTabKeydown(event: KeyboardEvent) {
    if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
    event.preventDefault();
    const index = PAGE_TABS.indexOf(this.activeTab);
    const next =
      event.key === "Home"
        ? 0
        : event.key === "End"
          ? PAGE_TABS.length - 1
          : (index + (event.key === "ArrowRight" ? 1 : -1) + PAGE_TABS.length) %
            PAGE_TABS.length;
    this.changeTab(PAGE_TABS[next], true);
  }

  content() {
    const tabs = app.forum.attribute<ProfileTab[]>("profilePrivacyTabs") || [];
    const selected = app.forum.attribute<string[]>("profileHiddenTabs") || [];
    // All three tabs share ExtensionPage's settings streams and save/reset controls.
    for (const tab of tabs) {
      this.setting(
        `huseyinfiliz-private-profile.hide_${tab.id}`,
        selected.includes(tab.id) ? "1" : "0",
        this.label(tab),
      );
    }
    this.setting(
      VISIBILITY_SETTING,
      app.forum.attribute<string>("profileVisibilityDefault") || "everyone",
      app.translator.trans(
        "huseyinfiliz-private-profile.admin.settings.forced_visibility_label",
      ),
    );
    this.setting(
      MODE_SETTING,
      app.forum.attribute<string>("profileVisibilityMode") || "member",
      app.translator.trans(
        "huseyinfiliz-private-profile.admin.settings.mode_label",
      ),
    );
    this.setting(
      AUDIENCE_SETTING,
      app.forum.attribute<string>("profilePrivacyAudience") || "private",
      app.translator.trans(
        "huseyinfiliz-private-profile.admin.settings.private_audience_label",
      ),
    );

    return (
      <div className="PrivateProfilePage">
        <Form className="PrivateProfile-form">
          <div className="PrivateProfile-settingsHeader">
            <div
              className="PrivateProfile-supportTabs"
              role="tablist"
              aria-label={extractText(
                app.translator.trans(
                  "huseyinfiliz-private-profile.admin.settings.page_sections",
                ),
              )}
            >
              {PAGE_TABS.map((tab) => (
                <Button
                  key={tab}
                  id={`private-profile-${tab}-tab`}
                  role="tab"
                  aria-controls={`private-profile-${tab}-panel`}
                  aria-selected={this.activeTab === tab ? "true" : "false"}
                  tabindex={this.activeTab === tab ? 0 : -1}
                  className={`Button ${this.activeTab === tab ? "active" : ""}`}
                  onclick={() => this.changeTab(tab)}
                  onkeydown={(event: KeyboardEvent) => this.onTabKeydown(event)}
                >
                  <Icon
                    name={
                      tab === "supported"
                        ? "fas fa-shield-alt"
                        : tab === "pageOnly"
                          ? "far fa-eye"
                          : "fas fa-cog"
                    }
                  />
                  {app.translator.trans(
                    `huseyinfiliz-private-profile.admin.settings.${tab === "supported" ? "supported_group" : tab === "pageOnly" ? "page_only_group" : "settings_group"}`,
                  )}
                  {tab !== "settings" && (
                    <span className="Button-badge">
                      {
                        tabs.filter(
                          (entry) =>
                            !!entry.apiProtected === (tab === "supported"),
                        ).length
                      }
                    </span>
                  )}
                </Button>
              ))}
            </div>
            <div className="PrivateProfile-actions">
              <TabDiscovery
                ondiscover={(tabs: ProfileTab[]) =>
                  app.forum.pushAttributes({ profilePrivacyTabs: tabs })
                }
              />
            </div>
          </div>
          <div
            className="PrivateProfile-settingsContent"
            role="tabpanel"
            id={`private-profile-${this.activeTab}-panel`}
            aria-labelledby={`private-profile-${this.activeTab}-tab`}
          >
            {this.activeTab === "settings"
              ? this.visibilitySettings()
              : this.profileTabs(tabs, selected)}
          </div>
          <div className="PrivateProfile-saveControls">
            {this.submitButton()}
            {this.resetButton(undefined, undefined, this.extension.id)}
          </div>
        </Form>
      </div>
    );
  }

  private visibilitySettings() {
    const memberMode = this.setting(MODE_SETTING)() !== "admin";
    return (
      <div className="PrivateProfile-generalSettings">
        <h3>
          {app.translator.trans(
            "huseyinfiliz-private-profile.admin.settings.settings_group",
          )}
        </h3>
        {this.buildSettingComponent({
          setting: MODE_SETTING,
          containerClassName: "PrivateProfile-modeSetting",
          type: "select",
          label: app.translator.trans(
            "huseyinfiliz-private-profile.admin.settings.mode_label",
          ),
          options: {
            member: app.translator.trans(
              "huseyinfiliz-private-profile.admin.settings.mode_member",
            ),
            admin: app.translator.trans(
              "huseyinfiliz-private-profile.admin.settings.mode_admin",
            ),
          },
          default: "member",
        })}
        <div
          className="PrivateProfile-supportInfo PrivateProfile-visibilityInfo"
          role="note"
        >
          <Icon name="fas fa-info-circle" />
          <div>
            <p>
              {app.translator.trans(
                "huseyinfiliz-private-profile.admin.settings.visibility_scope",
              )}
            </p>
            <p>
              {app.translator.trans(
                `huseyinfiliz-private-profile.admin.settings.${memberMode ? "member_mode_help" : "admin_mode_help"}`,
              )}
            </p>
            {memberMode ? (
              <p>
                {app.translator.trans(
                  "huseyinfiliz-private-profile.admin.settings.member_scope_help",
                )}
              </p>
            ) : (
              (["everyone", "members", "private"] as const).map((level) => (
                <p>
                  <strong>
                    {app.translator.trans(
                      `huseyinfiliz-private-profile.admin.settings.option_${level}`,
                    )}
                    :{" "}
                  </strong>
                  {app.translator.trans(
                    `huseyinfiliz-private-profile.admin.settings.visibility_${level}`,
                  )}
                </p>
              ))
            )}
            <p>
              {app.translator.trans(
                "huseyinfiliz-private-profile.admin.settings.preferences_preserved",
              )}
            </p>
          </div>
        </div>
        {memberMode
          ? this.buildSettingComponent({
              setting: AUDIENCE_SETTING,
              containerClassName: "PrivateProfile-audienceSetting",
              type: "select",
              label: app.translator.trans(
                "huseyinfiliz-private-profile.admin.settings.private_audience_label",
              ),
              help: app.translator.trans(
                "huseyinfiliz-private-profile.admin.settings.private_audience_help",
              ),
              options: {
                members: app.translator.trans(
                  "huseyinfiliz-private-profile.admin.settings.audience_members",
                ),
                private: app.translator.trans(
                  "huseyinfiliz-private-profile.admin.settings.audience_private",
                ),
              },
              default: "private",
            })
          : this.buildSettingComponent({
              setting: VISIBILITY_SETTING,
              containerClassName: "PrivateProfile-forcedSetting",
              type: "select",
              label: app.translator.trans(
                "huseyinfiliz-private-profile.admin.settings.forced_visibility_label",
              ),
              help: app.translator.trans(
                "huseyinfiliz-private-profile.admin.settings.forced_visibility_help",
              ),
              options: {
                everyone: app.translator.trans(
                  "huseyinfiliz-private-profile.admin.settings.option_everyone",
                ),
                members: app.translator.trans(
                  "huseyinfiliz-private-profile.admin.settings.option_members",
                ),
                private: app.translator.trans(
                  "huseyinfiliz-private-profile.admin.settings.option_private",
                ),
              },
              default:
                app.forum.attribute<string>("profileVisibilityDefault") ||
                "everyone",
            })}
      </div>
    );
  }

  private profileTabs(tabs: ProfileTab[], selected: string[]) {
    const visible = tabs.filter(
      (tab) => !!tab.apiProtected === (this.activeTab === "supported"),
    );
    return (
      <div className="PrivateProfile-tabSettings">
        <h3>
          {app.translator.trans(
            "huseyinfiliz-private-profile.admin.settings.hidden_tabs_label",
          )}
        </h3>
        <p className="helpText PrivateProfile-introduction">
          {app.translator.trans(
            "huseyinfiliz-private-profile.admin.settings.hidden_tabs_help",
          )}
        </p>
        <div
          className={`PrivateProfile-supportInfo PrivateProfile-supportInfo--${this.activeTab}`}
          role="note"
        >
          <Icon
            name={
              this.activeTab === "supported"
                ? "fas fa-shield-alt"
                : "fas fa-info-circle"
            }
          />
          <div>
            <p>
              {app.translator.trans(
                `huseyinfiliz-private-profile.admin.settings.${this.activeTab === "supported" ? "api_supported_help" : "api_unsupported_help"}`,
              )}
            </p>
            {this.activeTab === "pageOnly" && (
              <p className="PrivateProfile-apiRequest">
                {app.translator.trans(
                  "huseyinfiliz-private-profile.admin.settings.api_request",
                  {
                    a: m("a", {
                      href: "https://discuss.flarum.org/d/36967-private-profile-extension",
                      target: "_blank",
                      rel: "noopener noreferrer",
                    }),
                  },
                )}
              </p>
            )}
          </div>
        </div>
        <div className="PrivateProfile-tabList">
          {visible.map((tab) => (
            <div
              key={tab.id}
              className="PrivateProfile-tabOption"
              data-tab-id={tab.id}
              data-api-protected={String(!!tab.apiProtected)}
            >
              {this.buildSettingComponent({
                setting: `huseyinfiliz-private-profile.hide_${tab.id}`,
                type: "bool",
                label: this.label(tab),
                default: selected.includes(tab.id),
              })}
            </div>
          ))}
        </div>
        {!visible.length && (
          <div className="PrivateProfile-emptyState">
            <Icon name="far fa-folder-open" />
            <p>
              {app.translator.trans(
                "huseyinfiliz-private-profile.admin.settings.no_tabs",
              )}
            </p>
          </div>
        )}
      </div>
    );
  }
}
