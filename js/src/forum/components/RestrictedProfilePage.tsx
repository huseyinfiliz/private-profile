import app from "flarum/forum/app";
import UserPage from "flarum/forum/components/UserPage";
import PageStructure from "flarum/forum/components/PageStructure";
import Placeholder from "flarum/common/components/Placeholder";
import Button from "flarum/common/components/Button";
import type Mithril from "mithril";
import type { IUserPageAttrs } from "flarum/forum/components/UserPage";

export function restrictedView(page: UserPage) {
  const membersOnly =
    page.user?.attribute<string>("effectiveProfileVisibility") === "members";
  return (
    <PageStructure
      className="UserPage UserPage--private"
      loading={!page.user}
      hero={page.hero.bind(page)}
      sidebar={page.sidebar.bind(page)}
    >
      <div className="PrivateProfile-message">
        <Placeholder
          text={app.translator.trans(
            `huseyinfiliz-private-profile.forum.${membersOnly ? "profile_members_only" : "profile_is_private"}`,
          )}
        />
        {membersOnly && !app.session.user && (
          <Button
            className="Button Button--primary"
            onclick={() =>
              app.modal.show(() => import("flarum/forum/components/LogInModal"))
            }
          >
            {app.translator.trans("core.forum.header.log_in_link")}
          </Button>
        )}
      </div>
    </PageStructure>
  );
}

export default class RestrictedProfilePage extends UserPage {
  oninit(vnode: Mithril.Vnode<IUserPageAttrs, this>) {
    super.oninit(vnode);
    this.loadUser(m.route.param("username"));
  }

  view() {
    return restrictedView(this);
  }
}
