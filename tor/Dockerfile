# Minimal, self-contained Tor image for the hidden service.
#
# We build this ourselves instead of pulling `alpine/tor` — that Docker Hub
# image was removed and broke deploys ("pull access denied for alpine/tor").
# Alpine's `tor` package pulls in a maintained tor and creates the `tor` user
# that torrc drops to.
FROM alpine:3.20

RUN apk add --no-cache tor

# The stack starts this container as root and runs a wrapper (see the `tor`
# service command in docker-compose.yml) that fixes ownership of the
# bind-mounted key dir, then execs `tor`, which drops to the `tor` user.
