# NSS Vote

## Author

This code was written by Kim Roar Foldøy Hauge

## Description

A quick and secure online voting system. Focus was on code clarity, security and a simple design with little room for bugs. The lyout and design is minimalistic and simple. 

## Usage

Candidates are added by hand with a suitable mysql manipulation tool. PHMyAdmin will work just fine.

Students can then request a vote key, which is based on their student id. Rerequesting a vote key will produce the same vote key for the same student id. All tracking of votes having been cast is based on the vote key. The system only tracks whether a vote key has been cast and the amount of votes a candidate receives, not who voted what, to ensure anonymity.

## Installation

Add an appropriate SQL databse with the correct tables, edit the config file. Use SSL to ensure privacy and reduce the chance for voter fraud.

## Security

The system is most likely not fool-proof, there might be race conditions and other problems associated with it. It was considered to be more secure than the previous systems of putting paper notes with names written on them in a hat or using It's Learning for non-intended uses.
