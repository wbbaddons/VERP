WCF_FILES = $(shell find files_wcf -type f)

all: be.bastelstu.verp.tar

be.bastelstu.verp.tar: files_wcf.tar *.xml LICENSE
	tar cvf be.bastelstu.verp.tar --numeric-owner --exclude-vcs -- files_wcf.tar *.xml LICENSE

files_wcf.tar: $(WCF_FILES)
	tar cvf files_wcf.tar --numeric-owner --exclude-vcs --transform='s,files_wcf/,,' -- $^
clean:
	-rm -f files_wcf.tar

distclean: clean
	-rm -f be.bastelstu.verp.tar

.PHONY: distclean clean
