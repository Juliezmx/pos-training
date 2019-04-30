package app;

import templatebuilder.TemplateBuilder;
import virtualui.VirtualUIFrame;
import virtualui.VirtualUIImage;
import virtualui.VirtualUILabel;
/**
 * 
 * @author Juliezhang_20190416
 *
 */
public class FrameDisplayInfo extends VirtualUIFrame implements FrameCommonBasketListener{
	TemplateBuilder m_oTemplateBuilder;
	
	private VirtualUILabel m_oLabelOuletCodeHeader;
	private VirtualUILabel m_oLabelOuletCode;
	private VirtualUILabel m_oLabelCoverHeader;
	private VirtualUILabel m_oLabelCover;
	private VirtualUILabel m_oLabelUserNameHeader;
	private VirtualUILabel m_oLabelUserName;
	private VirtualUILabel m_oLabelBussinessDateHeader;
	private VirtualUILabel m_oLabelBussinessDate;
	private VirtualUILabel m_oLabelStationNameHeader;
	private VirtualUILabel m_oLabelStationName;
	
	@Override
	public void frameCommonBasketSection_SectionClicked(int iSectionId, String sNote) {
		// TODO Auto-generated method stub
		
	}

	@Override
	public void frameCommonBasketCell_FieldClicked(int iBasketId, int iSectionIndex, int iItemIndex, int iFieldIndex,
			String sNote) {
		// TODO Auto-generated method stub
		
	}

	@Override
	public void frameCommonBasketCell_FieldLongClicked(int iBasketId, int iSectionIndex, int iItemIndex,
			int iFieldIndex, String sNote) {
		// TODO Auto-generated method stub
		
	}

	@Override
	public void frameCommonBasketCell_HeaderClicked(int iFieldIndex) {
		// TODO Auto-generated method stub
		
	}

}
