import app from "flarum/admin/app";
import Component from "flarum/common/Component";
import type { ComponentAttrs } from "flarum/common/Component";
import Button from "flarum/common/components/Button";
import type Mithril from "mithril";
import type { ProfileTab } from "../../forum/profileTabs";

interface Attrs extends ComponentAttrs {
  ondiscover: (tabs: ProfileTab[]) => void;
}

export default class TabDiscovery extends Component<Attrs> {
  private frame?: HTMLIFrameElement;
  private nonce = "";
  private timer?: ReturnType<typeof setTimeout>;
  private received = false;
  private source = "";
  private loading = true;
  private failed = false;
  private alive = true;

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);
    window.addEventListener("message", this.receive);
    this.start();
  }

  private start() {
    clearTimeout(this.timer);
    this.nonce = "";
    this.source = "";
    this.received = false;
    this.loading = true;
    this.failed = false;
    try {
      const bytes = crypto.getRandomValues(new Uint8Array(16));
      this.nonce = Array.from(bytes, (byte) =>
        byte.toString(16).padStart(2, "0"),
      ).join("");
      const base = app.forum.attribute<string>("baseUrl").replace(/\/$/, "");
      this.source = `${base}/u/${encodeURIComponent(app.session.user!.slug())}?privateProfileDiscovery=${this.nonce}`;
      this.timer = setTimeout(() => {
        this.failed = true;
        this.loading = false;
        m.redraw();
      }, 20000);
    } catch {
      this.failed = true;
      this.loading = false;
    }
  }

  private receive = async (event: MessageEvent) => {
    if (
      !this.loading ||
      event.origin !== location.origin ||
      event.source !== this.frame?.contentWindow ||
      event.data?.type !== "private-profile.tabs" ||
      event.data?.nonce !== this.nonce ||
      this.received
    )
      return;
    this.received = true;
    clearTimeout(this.timer);
    try {
      const result = await app.request<{ tabs: ProfileTab[] }>({
        method: "POST",
        url: app.forum.attribute("apiUrl") + "/private-profile/tabs/discover",
        body: { tabs: event.data.tabs },
      });
      if (this.alive) this.attrs.ondiscover(result.tabs);
    } catch {
      this.failed = true;
    } finally {
      if (this.alive) {
        this.loading = false;
        m.redraw();
      }
    }
  };

  onremove(vnode: Mithril.VnodeDOM<Attrs, this>) {
    this.alive = false;
    clearTimeout(this.timer);
    window.removeEventListener("message", this.receive);
    super.onremove(vnode);
  }

  view() {
    const key = this.loading
      ? "discovering_tabs"
      : this.failed
        ? "discovery_failed"
        : "discovery_complete";
    return (
      <div className="PrivateProfile-discovery">
        <p className="helpText" aria-live="polite">
          {app.translator.trans(
            `huseyinfiliz-private-profile.admin.settings.${key}`,
          )}
        </p>
        <Button
          className="Button Button--primary PrivateProfile-refreshTabs"
          icon="fas fa-sync"
          disabled={this.loading}
          onclick={() => {
            this.start();
            m.redraw();
          }}
        >
          {app.translator.trans(
            "huseyinfiliz-private-profile.admin.settings.refresh_tabs",
          )}
        </Button>
        {this.loading && (
          <iframe
            className="PrivateProfile-discoveryFrame"
            hidden
            src={this.source}
            title="Profile tab discovery"
            oncreate={(vnode: Mithril.VnodeDOM) => {
              this.frame = vnode.dom as HTMLIFrameElement;
            }}
          />
        )}
      </div>
    );
  }
}
