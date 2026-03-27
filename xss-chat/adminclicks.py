import argparse
import re
from pathlib import Path

from selenium import webdriver
from selenium.webdriver.chrome.options import Options


parser = argparse.ArgumentParser("adminclicks")
parser.add_argument("url", help="Website for the admin to click.")
args = parser.parse_args()


def is_docker():
    """Checks if we currently run within a docker container"""
    # https://stackoverflow.com/a/73564246
    cgroup = Path("/proc/self/cgroup")
    return Path("/.dockerenv").is_file() or (
        cgroup.is_file() and "docker" in cgroup.read_text(encoding="utf-8")
    )


options = Options()
options.add_argument("--headless")
options.add_argument("--no-sandbox")
options.add_argument("--disable-gpu")
driver = webdriver.Chrome(options=options)

# Setting the cookie
driver.get("http://localhost")
driver.add_cookie({"domain": "localhost", "name": "session", "value": "1tW0rk5!4real"})

if is_docker():
    # Small hack to remove the port numbers for localhost. Inside the container it is just port 80
    # XXX: Maybe this can be done via a firewall rule?
    args.url = re.sub(r"(localhost)(:|%3A)\d+", r"\1", args.url)

driver.get(args.url)
print(driver.page_source)
driver.quit()
