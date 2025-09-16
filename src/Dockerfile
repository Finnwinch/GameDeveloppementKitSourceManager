FROM steamcmd/steamcmd:debian-bullseye

RUN useradd -m steam
RUN mkdir -p /home/steam/gmod && chown -R steam:steam /home/steam
COPY entrypoint.sh /home/steam/entrypoint.sh
RUN chmod +x /home/steam/entrypoint.sh && chown steam:steam /home/steam/entrypoint.sh
RUN dpkg --add-architecture i386 && \
    apt-get update && \
    apt-get install -y --no-install-recommends \
        lib32stdc++6 libc6-i386 ca-certificates curl && \
    rm -rf /var/lib/apt/lists/*
RUN steamcmd +force_install_dir /home/steam/gmod \
    +login anonymous \
    +app_update 232330 validate \
    +app_update 4020 validate \
    +quit

USER steam
WORKDIR /home/steam

ENTRYPOINT ["/home/steam/entrypoint.sh"]
