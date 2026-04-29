# glassbox-ctf
Learn hacking by seeing what’s happening at the victim


## Developer Setup

When changing `base/`, rebuild it first (from the repo root):

```sh
podman build -t glassbox-base ./base/
```

Then `cd` into a challenge directory and run:

```sh
podman build -t glassbox-ctf . && podman run --rm -p 9000:80 glassbox-ctf
```