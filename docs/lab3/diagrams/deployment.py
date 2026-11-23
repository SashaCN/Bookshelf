"""Figure fig:deployment: the deployment scheme of Bookshelf (nodes, containers, ports, volumes, external services).

Run from the repository root:  python3 docs/lab3/diagrams/deployment.py
The picture is written to docs/lab3/figures/deployment.png (a landscape figure, 1750 units wide).
"""

from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / 'docs' / 'tools'))

from diagram import BLUE, GREEN, GREY, LIGHT, RED, WHITE, YELLOW, Canvas, disk  # noqa: E402

W, H = 1750, 940
SIZE = 18


def label(c: Canvas, x: float, y: float, text: str, anchor: str = 'mm', size: float = SIZE, italic: bool = False) -> None:
    """A text with a white patch behind it, so that a line or a frame under the text does not run through the letters."""
    lines = text.split('\n')
    width = max(c.measure(line, size, italic=italic) for line in lines) + 10
    height = len(lines) * size * 1.15 + 4
    left = x - width / 2 if anchor[0] == 'm' else (x if anchor[0] == 'l' else x - width)
    top = y - height / 2 if anchor[1] == 'm' else (y if anchor[1] == 't' else y - height)
    c.rect(left, top, width, height, WHITE, None, 0)
    c.text(left + width / 2, top + height / 2, text, size, italic=italic, anchor='mm')


def node(c: Canvas, x, y, w, h, title: str, body: str, fill: str, size: float = SIZE) -> None:
    c.rect(x, y, w, h, fill, 'black', 2, 10)
    lines = body.count('\n') + 1
    block = (1 + lines) * size * 1.15 + 6
    top = y + (h - block) / 2
    c.text(x + w / 2, top + size * 0.58, title, size + 1, bold=True)
    c.text(x + w / 2, top + size * 1.15 + 6 + (lines * size * 1.15) / 2 + 2, body, size)


def draw() -> Canvas:
    c = Canvas(W, H)

    # ---- columns ---------------------------------------------------------------------------------------------------
    left_x, left_w = 20, 290
    vm_x, vm_w = 500, 750
    right_x, right_w = 1440, 290
    vm_y, vm_h = 15, 910

    # ---- the virtual machine -----------------------------------------------------------------------------------------
    c.rect(vm_x, vm_y, vm_w, vm_h, LIGHT, 'black', 3, 14)
    c.text(vm_x + vm_w / 2, vm_y + 24, 'Azure virtual machine Standard_B2ats_v2 (Denmark East)', 20, bold=True)
    c.text(vm_x + vm_w / 2, vm_y + 54, 'Ubuntu Server 24.04 x64 · 2 vCPU · 1 GiB RAM · 30 GB Standard SSD · swap 2 GB', SIZE)

    # the host level: ssh, cron and the files of the deployment
    host = (vm_x + 20, 95, vm_w - 40, 175)
    c.rect(*host, YELLOW, 'black', 2, 8)
    c.text(host[0] + host[2] / 2, host[1] + 24, 'Host system (Docker Engine and Compose)', SIZE + 1, bold=True)
    c.text_in(host[0], host[1] + 40, host[2], host[3] - 40,
              'Azure network rules: inbound 22, 80, 443 · sshd :22 (public key only)\n'
              'cron 03:00 runs backup.sh (set by hand on the server)\n'
              '/opt/bookshelf: production.env, docker-compose.prod.yml, Caddyfile,\n'
              'rollout.sh, backup.sh, backups/',
              SIZE, pad=4, anchor='mt')

    # the Compose project
    comp_x, comp_y, comp_w, comp_h = vm_x + 20, 295, vm_w - 40, 610
    c.rect(comp_x, comp_y, comp_w, comp_h, WHITE, 'black', 2, 10, dash=(10, 7))
    c.text(comp_x + comp_w / 2, comp_y + 20, 'Docker Compose project “bookshelf” (restart: unless-stopped)', SIZE, italic=True)

    cx, cw = comp_x + comp_w - 20 - 350, 350  # the column of containers (right side of the project)
    caddy = (cx, 335, cw, 100)
    web = (cx, 490, cw, 100)
    app = (cx, 645, cw, 130)
    mysql = (cx, 830, cw, 100 - 0)
    mysql = (cx, 810, cw, 90)

    def container(box, title, body, fill):
        node(c, *box, title, body, fill)

    container(caddy, 'caddy', 'caddy:2-alpine · ports 80, 443\nTLS certificate, gzip, HSTS', GREY)
    container(web, 'web', 'nginx:1.28-alpine (bookshelf-web)\nSPA files, proxy of /api, /docs, /up', GREY)
    container(app, 'app', 'php:8.4-fpm-alpine (bookshelf-app) :9000\nLaravel API · pm ondemand, 6 workers\nmemory_limit 256M · opcache 128M', BLUE)
    container(mysql, 'mysql', 'mysql:8.4 :3306 · buffer pool 64M\nmax_connections 40 · healthcheck', YELLOW)

    mid = cx + cw / 2
    for upper, lower, text in ((caddy, web, 'HTTP :80'), (web, app, 'FastCGI :9000'), (app, mysql, 'SQL :3306')):
        y1, y2 = upper[1] + upper[3], lower[1]
        c.arrow([(mid, y1), (mid, y2)], 2, 'end')
        label(c, mid + 12, (y1 + y2) / 2, text, 'lm')

    # the volumes (the left part of the project)
    vol_x, vol_w = comp_x + 20, 250
    disk(c, vol_x, 335, vol_w, 100, 'volumes caddy_data,\ncaddy_config\n(certificates)', GREEN, SIZE)
    disk(c, vol_x, 810, vol_w, 90, 'volume mysql_data\n(the whole database)', GREEN, SIZE)
    c.arrow([(cx, 385), (vol_x + vol_w, 385)], 2, 'both')
    c.arrow([(cx, 855), (vol_x + vol_w, 855)], 2, 'both')

    # a note about the published ports
    c.rect(vol_x, 490, vol_w, 285, WHITE, 'black', 1.5, 8)
    c.text_in(vol_x, 490, vol_w, 285,
              'Only caddy publishes ports (80 and 443).\n\nweb:80, app:9000 and mysql:3306 are open inside the Compose network only.\n\n'
              'No memory limits are set for the containers.',
              SIZE, pad=10, align='left', anchor='lt')

    # ---- the right side: readers, certificates, Open Library -------------------------------------------------------
    reader = (right_x, 330, right_w, 150)
    node(c, *reader, 'Device of a reader',
         'phone or computer\nbrowser with the PWA,\nservice worker; loads covers from\ncovers.openlibrary.org', GREY)
    certs = (right_x, 520, right_w, 110)
    node(c, *certs, "Let's Encrypt, sslip.io", 'certificates (ACME);\nname 20-1-2-3.sslip.io', GREY)
    olib = (right_x, 665, right_w, 120)
    node(c, *olib, 'Open Library', 'openlibrary.org\nsearch API (read only),\nanswers cached 24 h', GREY)

    gate = vm_x + vm_w  # right border of the virtual machine
    # reader <-> caddy
    c.arrow([(cx + cw, 350), (right_x, 350)], 2, 'both')
    label(c, (gate + right_x) / 2 + 20, 332, 'HTTPS 443', 'mm')
    c.arrow([(cx + cw, 385), (right_x, 385)], 2, 'both')
    label(c, (gate + right_x) / 2 + 20, 367, 'HTTP 80', 'mm')
    # caddy <-> certificates
    c.arrow([(cx + cw, 420), (1345, 420), (1345, 565), (right_x, 565)], 2, 'both')
    label(c, 1345, 492, 'ACME,\nHTTPS 443', 'mm')
    # app -> Open Library
    c.arrow([(cx + cw, 725), (right_x, 725)], 2, 'both')
    label(c, (gate + right_x) / 2 + 20, 700, 'HTTPS 443', 'mm')

    # ---- the left side: GitHub and the workstation -----------------------------------------------------------------
    github = (left_x, 95, left_w, 120)
    node(c, *github, 'GitHub', 'repository (branch main)\nActions: checks and deployment\nGHCR: images bookshelf-app,\nbookshelf-web (linux/amd64)', GREY)
    work = (left_x, 275, left_w, 160)
    node(c, *work, 'Workstation (Mac)',
         'developer and administrator\nGit, SSH key bookshelf_deploy\nlocal stack: web :8000,\nvite :5173, mysql 127.0.0.1:3308', GREY)

    c.arrow([(left_x + left_w / 2, 275), (left_x + left_w / 2, 215)], 2, 'end')
    label(c, left_x + left_w / 2 + 14, 245, 'git push', 'lm')

    gap_mid = (left_x + left_w + vm_x) / 2
    host_left = host[0]
    # Actions -> host: scp and rollout.sh over SSH
    c.arrow([(left_x + left_w, 125), (host_left, 125)], 2, 'end')
    label(c, gap_mid, 106, 'SSH 22: rollout.sh', 'mm')
    # host -> GHCR: docker pull
    c.arrow([(host_left, 175), (left_x + left_w, 175)], 2, 'end')
    label(c, gap_mid, 196, 'HTTPS 443: image pull', 'mm')
    # workstation -> host: administration, moderation commands
    c.arrow([(left_x + left_w, 345), (400, 345), (400, 245), (host_left, 245)], 2, 'end')
    label(c, 400, 295, 'SSH 22:\nadmin,\nmoderation', 'mm')

    # ---- legend ----------------------------------------------------------------------------------------------------
    c.rect(left_x, 480, left_w, 345, WHITE, 'black', 1.5, 8)
    c.text(left_x + left_w / 2, 505, 'Legend', SIZE + 1, bold=True)
    c.text_in(left_x, 520, left_w, 295,
              'A line with arrows is a network connection; the label gives the protocol and the port.\n\n'
              'A dashed frame is the Docker Compose project.\n\n'
              'A disk symbol is a Docker volume.\n\n'
              'Ports 80 and 443 are open to the Internet; sign-in over port 22 is by a public key.',
              SIZE, pad=12, align='left', anchor='lt')
    return c


if __name__ == '__main__':
    out = ROOT / 'docs' / 'lab3' / 'figures' / 'deployment.png'
    draw().save(out)
    print('written', out)
