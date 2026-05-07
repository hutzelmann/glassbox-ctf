import argparse
import json
import re

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service


parser = argparse.ArgumentParser("adminclicks")
parser.add_argument("url", help="Website for the admin to click.")
args = parser.parse_args()


options = Options()
options.add_argument("--headless")
options.add_argument("--no-sandbox")
options.add_argument("--disable-gpu")
options.set_capability("goog:loggingPrefs", {"browser": "ALL"})
options.binary_location = "/usr/bin/chromium"
service = Service("/usr/bin/chromedriver")
driver = webdriver.Chrome(service=service, options=options)

# Setting the cookie
driver.get("http://localhost")
driver.add_cookie({"domain": "localhost", "name": "session", "value": "1tW0rk5!4real"})

# Strip any host-mapped port from localhost URLs — inside the container it is always port 80
args.url = re.sub(r"(localhost)(:|%3A)\d+", r"\1", args.url)

driver.get(args.url)
logs = driver.get_log("browser")
print(json.dumps({"js_errors": logs, "page_source": driver.page_source}))
driver.quit()
