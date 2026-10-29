import app from "flarum/forum/app";
import { extend, override } from "flarum/common/extend";
import Placeholder from "flarum/common/components/Placeholder";
import type UserPage from "flarum/forum/components/UserPage";

function isPrivate(page: UserPage): boolean {
  return (
    page.user?.attribute<boolean>("privateProfile") === true &&
    app.session.user?.id() !== page.user.id() &&
    !app.session.user?.isAdmin()
  );
}

export default function () {
  extend<UserPage, "view">(
    "flarum/forum/components/UserPage",
    "view",
    function (vnode) {
      if (isPrivate(this)) {
        const attrs = vnode.attrs as { className?: string };
        attrs.className = `${attrs.className || ""} UserPage--private`;
      }
    },
  );

  for (const component of ["PostsUserPage", "DiscussionsUserPage"]) {
    override<UserPage, "content">(
      `flarum/forum/components/${component}`,
      "content",
      function (original) {
        return isPrivate(this) ? (
          <Placeholder
            text={app.translator.trans(
              "huseyinfiliz-private-profile.forum.profile_is_private",
            )}
          />
        ) : (
          original()
        );
      },
    );
  }
}
