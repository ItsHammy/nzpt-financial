"""
    THIS SCRIPT WILL BE REPLACED WITH CRON JOBS OR GH ACTIONS IN THE FUTURE.
    CURRENTLY BEING USED AS A TEMPORARY SOLUTION TO RUN THE SCRAPE SCRIPT EVERY 30 MINUTES. 
"""
import time
from scrape import *

def run():
    while True:
        print("Running scrape...")
        asyncio.run(main())
        print("Sleeping for 30 minutes...")
        time.sleep(900) # Sleep for 15 minutes
        print("15 minutes has elapsed (script sanity check)")
        time.sleep(600) # Sleep for another 10 minutes
        print("25 minutes has elapsed (script sanity check 2)")
        time.sleep(300) # Sleep for another 5 minutes to complete the 30 minute cycle
        print("Waking up...")
        
        
if __name__ == "__main__":
    run()
