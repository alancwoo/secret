#!/bin/bash
rsync -avz --exclude '.git*' --exclude 'node_modules' --exclude '.DS_Store' --exclude 'database' --exclude '.env' --exclude '*.sh' . runcloud:~/webapps/secret/
