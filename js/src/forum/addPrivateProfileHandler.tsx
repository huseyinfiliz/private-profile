import app from "flarum/forum/app";
import { extend, override } from "flarum/common/extend";
import DefaultResolver from "flarum/common/resolvers/DefaultResolver";
import RestrictedProfilePage, {
  restrictedView,
} from "./components/RestrictedProfilePage";
import type User from "flarum/common/models/User";
import type UserPage from "flarum/forum/components/UserPage";
import { isProtectedTab } from "./profileTabs";
import type Mithril from "mithril";

export default function () {
  // Gate profile routes before the original page initializes or requests its tab data.
  override(
    DefaultResolver.prototype,
    "onmatch",
    async function (original, args, requestedPath, route) {
      if (
        /\/u\/:username(?:\/|$)/.test(route) &&
        typeof args.username === "string" &&
        isProtectedTab(requestedPath)
      ) {
        const user = await app.store.find<User>("users", args.username, {
          bySlug: true,
        });
        if (user.attribute<boolean>("canViewProfile") === false)
          return RestrictedProfilePage;
      }
      return original(args, requestedPath, route);
    },
  );

  // Also cover UserPage subclasses routed through custom resolvers.
  override<UserPage, "view">(
    "flarum/forum/components/UserPage",
    "view",
    function (original) {
      return this.user?.attribute<boolean>("canViewProfile") === false &&
        isProtectedTab(m.route.get())
        ? restrictedView(this)
        : original();
    },
  );

  app.beforeMount(() =>
    extend<UserPage, "navItems">(
      "flarum/forum/components/UserPage",
      "navItems",
      function (items) {
        if (this.user?.attribute<boolean>("canViewProfile") !== false) return;
        for (const item of items.toArray()) {
          const vnode = item as Mithril.Vnode<{
            href?: string;
            className?: string;
          }>;
          if (vnode?.attrs?.href && isProtectedTab(vnode.attrs.href)) {
            vnode.attrs.className = `${vnode.attrs.className || ""} PrivateProfile-restrictedTab`;
          }
        }
      },
    ),
  );
}
