这是一个奇怪的项目。

用于监控某个拥有非固定外网IP的IP变化。

原理是这样的，基于 Workerman 在外网一台有固定IP的机器上开启 tcp 连接用于接收IP变化和广播IP变化。
这台机器我们称之为 **Server**。

报告端连接上这个固定IP的机器，当连接断开后会重连，上报变化后的IP。
报告端称之为 **Reporter**。

需要监听 Reporter 所在网的 IP 端称之为 **Listener**，Listener 也会连接着 Server，当 Server 将 IP 广播到 Listener 时，Listener 可以连接这个IP去做一些奇怪的事情。

这个东西主要是为了解决国内非企业动态 IP 解析有延迟，且 DNS 本身缓存的及时性问题。
有了这个后，理论上 Reporter 端 IP 变化后，Listener 在几秒内（取决于设定的重连时间）就能得到到 Reporter 端的新IP。