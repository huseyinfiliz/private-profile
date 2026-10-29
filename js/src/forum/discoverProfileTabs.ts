import app from "flarum/forum/app";
import { extend } from "flarum/common/extend";
import extractText from "flarum/common/utils/extractText";
import type UserPage from "flarum/forum/components/UserPage";
import type Mithril from "mithril";
import { automaticTabId, pathMatches, type ProfileTab } from "./profileTabs";

function fallbackLabel(path: string): string {
  const label =
    path
      .replace(/^\//, "")
      .replace(/([a-z])([A-Z])/g, "$1 $2")
      .replace(/[-_/]+/g, " ") || "Posts";
  return label.charAt(0).toUpperCase() + label.slice(1);
}

function validPath(path: string): boolean {
  return (
    new TextEncoder().encode(path).length <= 128 &&
    !/^\/security(?:\/|$)/.test(path) &&
    (path === "" || /^\/(?:[\p{L}\p{N}_:.\-]+\/?)*$/u.test(path))
  );
}

function labelText(children: Mithril.Children): string {
  if (Array.isArray(children)) return children.map(labelText).join("");
  if (children && typeof children === "object") {
    const vnode = children as Mithril.Vnode<{ className?: string }>;
    if (vnode.attrs?.className?.split(" ").includes("Button-badge")) return "";
    return labelText(vnode.children);
  }
  return extractText(children);
}

export default function discoverProfileTabs() {
  app.beforeMount(() => {
    const known = app.forum.attribute<ProfileTab[]>("profilePrivacyTabs") || [];
    const tabs = new Map<string, ProfileTab>();
    for (const route of Object.values(app.routes)) {
      const match = route.path.match(/^\/u\/:username(\/.*)?$/);
      if (!match) continue;
      const path = (match[1] || "").replace(/\/$/, "");
      if (!validPath(path)) continue;
      const previous = known.find((tab) => tab.path === path);
      tabs.set(
        path,
        previous || {
          id: automaticTabId(path),
          path,
          label: fallbackLabel(path),
          apiProtected: false,
        },
      );
    }
    app.forum.pushAttributes({ profilePrivacyTabs: Array.from(tabs.values()) });
  });

  // Register after all initializers so extension-added links are already in the returned ItemList.
  app.beforeMount(() =>
    extend<UserPage, "navItems">(
      "flarum/forum/components/UserPage",
      "navItems",
      function (items) {
        const tabs =
          app.forum.attribute<ProfileTab[]>("profilePrivacyTabs") || [];
        for (const item of items.toArray()) {
          const vnode = item as Mithril.Vnode<{ href?: string }>;
          if (!vnode?.attrs?.href) continue;
          const match = vnode.attrs.href.match(/\/u\/[^/]+(\/[^?#]*)?/);
          if (!match) continue;
          const suffix = match[1]?.replace(/\/$/, "") || "";
          const tab = tabs.find((entry) => pathMatches(entry.path, suffix));
          const label = labelText(vnode.children).trim();
          if (tab && label) tab.label = label.slice(0, 120);
        }
      },
    ),
  );

  // The admin application cannot see forum JS routes, so its same-origin frame reports them.
  const nonce = new URLSearchParams(location.search).get(
    "privateProfileDiscovery",
  );
  if (nonce && window.parent !== window) {
    let sent = false;
    extend<UserPage, "oncreate" | "onupdate">(
      "flarum/forum/components/UserPage",
      ["oncreate", "onupdate"],
      function () {
        if (sent || !this.user || !app.session.user?.isAdmin()) return;
        sent = true;
        window.parent.postMessage(
          {
            type: "private-profile.tabs",
            nonce,
            tabs: (
              app.forum.attribute<ProfileTab[]>("profilePrivacyTabs") || []
            ).map((tab) => ({
              path: tab.path,
              label: tab.label || fallbackLabel(tab.path),
            })),
          },
          location.origin,
        );
      },
    );
  }
}
