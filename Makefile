# Makefile for deployment

PYTHON        = python

help:
	@echo "Please use \`make <target>' where <target> is one of"
	@echo "  zip                         build plugin zipfile"
	@echo "  bak                         remove *.bak files"


zip: ; zip -r dhlog_export_plugin.zip Core -x \*.gitkeep
bak: ; find . -name "*bak" | xargs rm
