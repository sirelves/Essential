# Essential + Bedrock 1.26.62 + native NetherNet

This branch (`nethernet-1.26.62`) is a **modified version** of [BakuTeam/Essential](https://github.com/BakuTeam/Essential)
(base commit `7b9f7cf8e`, "prepare 5.46.2 release") that adds:

1. **Minecraft: Bedrock Edition 1.26.60 / 1.26.62 (protocol 2225).** Every protocol change is gated on
   `>= ProtocolInfo::PROTOCOL_1_26_62`, so clients 1.12 – 1.26.45 behave as before.
   What changed and why: [bedrock-nethernet-bridge/docs/protocol-2225.md](https://github.com/sirelves/bedrock-nethernet-bridge/blob/main/docs/protocol-2225.md).
2. **Native NetherNet (WebRTC) transport.** Bedrock 26.60+ clients (Android, the new Windows client) only join
   third-party servers over NetherNet: `GET /v1/join` + `POST /v1/join/{id}` on the game port (TCP), then WebRTC data
   channels. RakNet keeps serving older clients on the same port (UDP). No proxy needed.

Tested with real Android 26.62 clients: join, walk, break/drop/pickup/place, chat, commands, recipe-book crafting,
chests, death/respawn (protocol 2225 over RakNet + bridge), and join/walk/break/chat over native NetherNet.

## Commits

| Commit | What |
|---|---|
| `feat: support Minecraft Bedrock 1.26.62 (protocol 2225)` | Protocol 2225 + 1.26.60 block palette |
| `build: bundle axolotl-pm/nethernet 0.3.2` | NetherNet server library in `lib/axolotl-nethernet` (PSR-4 `pocketmine\nethernet\`) |
| `feat(network): transport identity and encryption skip in NetworkSession` | Login key must match the key proven by the transport; Minecraft AES skipped only when the transport already encrypts |
| `build: include lib/ in the server phar` | |
| `feat(network): NetherNet (WebRTC) transport` | `src/network/mcpe/nethernet/*`, `transport.*` in `pocketmine.yml` |
| `feat: start NetherNet next to RakLib when transport.name lists it` | Opt-in; failures are logged and the server continues with RakNet only |

## Requirements

NetherNet needs the PHP extension **[ext-webrtc](https://github.com/axolotl-pm/ext-webrtc) 0.3.x** (built on
[libdatachannel](https://github.com/paullouisageneau/libdatachannel)). The PocketMine PHP binaries do not ship it.
Without it the server logs `Transport nethernet is unavailable and will be skipped` and runs RakNet only.

Built and tested with: PocketMine PHP binary **8.4.16 ZTS** (API `20240924`), libdatachannel **v0.24.5**,
ext-webrtc **0.3.1**, Ubuntu 24.04 x86_64 (GCC 13). The extension must be built against the **same PHP version and
ZTS mode** as `bin/php7/bin/php`.

### Building ext-webrtc (what we ran)

```sh
# 1. PHP headers + phpize matching the server's binary (bin/php7/bin/php -v)
tar xf php-8.4.16.tar.xz && cd php-8.4.16
./configure --prefix=$PWD/../phpdev --enable-zts --disable-all --disable-cgi --disable-phpdbg \
            --without-sqlite3 --without-pdo-sqlite
make -j4 && make install && cd ..

# 2. libdatachannel (shared, OpenSSL, no media/examples/tests)
git clone --branch v0.24.5 --recurse-submodules https://github.com/paullouisageneau/libdatachannel.git
cmake -S libdatachannel -B libdatachannel/build -DCMAKE_BUILD_TYPE=Release \
      -DCMAKE_INSTALL_PREFIX=$PWD/dc -DCMAKE_INSTALL_RPATH='$ORIGIN' \
      -DUSE_GNUTLS=OFF -DUSE_MBEDTLS=OFF -DUSE_NICE=OFF -DNO_MEDIA=ON -DNO_EXAMPLES=ON -DNO_TESTS=ON
cmake --build libdatachannel/build -j4 && cmake --install libdatachannel/build

# 3. ext-webrtc
git clone --branch 0.3.1 https://github.com/axolotl-pm/ext-webrtc.git && cd ext-webrtc
../phpdev/bin/phpize
./configure --with-php-config=$PWD/../phpdev/bin/php-config --enable-webrtc --with-libdatachannel=$PWD/../dc
make -j4 && cd ..
```

### Installing into the server's PHP

```sh
SERVER=/path/to/server
EXT=$SERVER/bin/php7/lib/php/extensions/no-debug-zts-20240924
cp -a dc/lib/libdatachannel.so* $SERVER/bin/php7/lib/
cp ext-webrtc/modules/webrtc.so $EXT/
patchelf --set-rpath '$ORIGIN/../../..' $EXT/webrtc.so   # finds bin/php7/lib/libdatachannel.so
echo 'extension=webrtc' >> $SERVER/bin/php7/bin/php.ini
cd $SERVER && ./bin/php7/bin/php -c bin/php7/bin/php.ini -m | grep webrtc
```

## Building the phar

```sh
composer install --no-dev
php -d phar.readonly=0 build/server-phar.php --out Essential.phar --git "$(git rev-parse HEAD)"
```

## Configuration (`pocketmine.yml`)

NetherNet is **off by default** (`transport.name: raknet`). Example used in our tests:

```yaml
transport:
  name: nethernet,raknet
  nethernet:
    key-file: nethernet.key          # server identity; keep it, or players are asked to trust the server again
    ice-udp-mux: true                # one UDP port for every WebRTC peer
    port-range: [19140, 19140]       # that UDP port (must differ from the game port)
    mtu: 1200
    advertise-addresses:
      - 203.0.113.10                 # the server's public IPv4
    ice-servers: []
    builtin-signaling:
      enabled: true
      port: 0                        # 0 = server-port, over TCP
```

Open **TCP `server-port`** (signaling), **UDP `server-port`** (RakNet) and the **UDP port in `port-range`** (WebRTC).
Check with `curl http://IP:PORT/v1/join` (must return JSON with `"transportLayer":2`).

## Security

- NetherNet sessions are encrypted by DTLS and the client proves its key during the `a=identity` handshake. The
  Minecraft AES handshake is skipped **only** when the transport reports both, and authenticated logins are rejected
  unless the Login key matches the proven key (prevents replaying a Login captured elsewhere). Keep `xbox-auth=on`.
- Sessions from `127.0.0.1` also skip Minecraft encryption and are not address-blocked by RakLib. This exists for
  [nethernet-bridge](https://github.com/sirelves/bedrock-nethernet-bridge); do not expose other local relays.

## License and credits

LGPL-3.0-or-later, like Essential and PocketMine-MP. Modified on 2026-11-10; no warranty.

- [BakuTeam/Essential](https://github.com/BakuTeam/Essential) (base), NetherGamesMC and pmmp (upstreams).
- NetherNet transport and `Transport*` interfaces ported from
  [axolotl-pm/PocketMine-MP](https://github.com/axolotl-pm/PocketMine-MP) (LGPL-3.0); library
  [axolotl-pm/NetherNet](https://github.com/axolotl-pm/NetherNet) 0.3.2 bundled with its license in `lib/axolotl-nethernet`.
- 1.26.60 block palette converted from [df-mc/dragonfly](https://github.com/df-mc/dragonfly) (MIT).
