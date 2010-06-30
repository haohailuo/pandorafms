Name:           perl-XML-Simple
Version:        2.18
Release:        1%{?dist}
Summary:        Easy API to maintain XML (esp config files)
License:        CHECK(GPL+ or Artistic)
Group:          Development/Libraries
URL:            http://search.cpan.org/dist/XML-Simple/
Source0:        http://www.cpan.org/authors/id/G/GR/GRANTM/XML-Simple-%{version}.tar.gz
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
BuildArch:      noarch
BuildRequires:  perl(XML::NamespaceSupport) >= 1.04
BuildRequires:  perl(XML::SAX)
Requires:       perl(XML::NamespaceSupport) >= 1.04
Requires:       perl(XML::SAX)
Requires:       perl(:MODULE_COMPAT_%(eval "`%{__perl} -V:version`"; echo $version))

%description
The XML::Simple module provides a simple API layer on top of an underlying
XML parsing module (either XML::Parser or one of the SAX2 parser modules).
Two functions are exported: XMLin() and XMLout(). Note: you can explicity
request the lower case versions of the function names: xml_in() and
xml_out().

%prep
%setup -q -n XML-Simple-%{version}

%build
%{__perl} Makefile.PL INSTALLDIRS=vendor
make %{?_smp_mflags}

%install
rm -rf $RPM_BUILD_ROOT

make pure_install PERL_INSTALL_ROOT=$RPM_BUILD_ROOT

find $RPM_BUILD_ROOT -type f -name .packlist -exec rm -f {} \;
find $RPM_BUILD_ROOT -depth -type d -exec rmdir {} 2>/dev/null \;

%{_fixperms} $RPM_BUILD_ROOT/*

%check
make test

%clean
rm -rf $RPM_BUILD_ROOT

%files
%defattr(-,root,root,-)
%doc Changes maketest README
%{perl_vendorlib}/*
%{_mandir}/man3/*

%changelog
* Wed Jun 30 2010 Sancho Lerena <slerena@artica.es> 2.18-1
- Specfile autogenerated by cpanspec 1.77.
